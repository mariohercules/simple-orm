<?php

declare(strict_types=1);

namespace SimpleORM\Model;

use SimpleORM\Exceptions\ModelNotFoundException;
use SimpleORM\Query\QueryBuilder;
use SimpleORM\Relations\Relation;

/**
 * Wraps a QueryBuilder and returns hydrated models instead of raw rows, which
 * removes the find()/get() array-vs-model asymmetry. Also drives eager loading.
 *
 * Unknown methods fall through to the underlying QueryBuilder via __call, so the
 * full fluent query surface is available; fluent calls return $this for chaining.
 *
 * @method static where(string $column, ?string $operator = null, mixed $value = null)
 * @method static whereIn(string $column, array $values)
 * @method static whereNull(string $column)
 * @method static whereNotNull(string $column)
 * @method static orderBy(string $column, string $direction = 'asc')
 * @method static groupBy(string ...$groups)
 * @method static having(string $column, string $operator, mixed $value)
 * @method static limit(int $value)
 * @method static offset(int $value)
 * @method static forPage(int $page, int $perPage = 15)
 * @method static join(string $table, string $first, string $operator, string $second)
 * @method static leftJoin(string $table, string $first, string $operator, string $second)
 * @method static select(array|string $columns = ['*'])
 * @method static distinct(bool $value = true)
 * @method int count(string $column = '*')
 * @method bool exists()
 * @method mixed value(string $column)
 * @method array pluck(string $column)
 * @method mixed max(string $column)
 * @method mixed min(string $column)
 * @method mixed sum(string $column)
 * @method mixed avg(string $column)
 */
class Builder
{
    protected Model $model;

    /** @var array<int,string> */
    protected array $eagerLoad = [];

    public function __construct(protected QueryBuilder $query)
    {
    }

    public function getQuery(): QueryBuilder
    {
        return $this->query;
    }

    public function setModel(Model $model): static
    {
        $this->model = $model;
        $this->query->from($model->getTable());

        return $this;
    }

    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * @param array<int,string>|string $columns
     * @return array<int,Model>
     */
    public function get(array|string $columns = ['*']): array
    {
        return $this->hydrateAndLoad($this->query->get($columns));
    }

    /**
     * Hydrate rows into models and run any queued eager loads.
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,Model>
     */
    protected function hydrateAndLoad(array $rows): array
    {
        $models = $this->hydrate($rows);

        if ($models !== [] && $this->eagerLoad !== []) {
            $models = $this->eagerLoadRelations($models);
        }

        return $models;
    }

    /**
     * @param array<int,string>|string $columns
     * @return array<int,Model>
     */
    public function all(array|string $columns = ['*']): array
    {
        return $this->get($columns);
    }

    /**
     * @param array<int,string>|string $columns
     */
    public function first(array|string $columns = ['*']): ?Model
    {
        return $this->limit(1)->get($columns)[0] ?? null;
    }

    /**
     * @param array<int,string>|string $columns
     */
    public function find(int|string $id, array|string $columns = ['*']): ?Model
    {
        return $this->whereKey($id)->first($columns);
    }

    public function findOrFail(int|string $id): Model
    {
        $result = $this->find($id);

        if ($result === null) {
            throw (new ModelNotFoundException())->setModel($this->model::class, $id);
        }

        return $result;
    }

    public function firstOrFail(): Model
    {
        $result = $this->first();

        if ($result === null) {
            throw (new ModelNotFoundException())->setModel($this->model::class);
        }

        return $result;
    }

    public function whereKey(int|string $id): static
    {
        $this->query->where($this->model->getKeyName(), '=', $id);

        return $this;
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function create(array $attributes): Model
    {
        $model = $this->model->newInstance($attributes);
        $model->save();

        return $model;
    }

    /**
     * @param array<string,mixed> $values
     */
    public function update(array $values): int
    {
        return $this->query->update($this->addUpdatedAtColumn($values));
    }

    public function delete(): int
    {
        return $this->query->delete();
    }

    /**
     * @param array<int,string>|string $relations
     */
    public function with(array|string $relations): static
    {
        $relations = is_string($relations) ? func_get_args() : $relations;

        foreach ($relations as $name) {
            $this->eagerLoad[$name] = $name;
        }

        return $this;
    }

    public function latest(?string $column = null): static
    {
        $this->query->orderBy($column ?? $this->model::CREATED_AT, 'desc');

        return $this;
    }

    public function oldest(?string $column = null): static
    {
        $this->query->orderBy($column ?? $this->model::CREATED_AT, 'asc');

        return $this;
    }

    /**
     * Keyset filter: rows whose primary key is greater than $id, ordered by key.
     * Far cheaper than OFFSET for deep pagination on large tables — OFFSET makes
     * the database walk and discard every skipped row, keyset seeks straight in.
     */
    public function whereKeyAfter(int|string $id): static
    {
        $key = $this->model->getKeyName();
        $this->query->where($key, '>', $id)->orderBy($key);

        return $this;
    }

    /**
     * Process results in keyset-ordered chunks of $size, holding only one chunk
     * in memory at a time. Stable under concurrent inserts (unlike OFFSET). Any
     * existing order is replaced by the key column, which keyset paging requires.
     *
     * @param callable(array<int,Model>):mixed $callback
     */
    public function chunkById(int $size, callable $callback, ?string $column = null): void
    {
        foreach ($this->keysetChunks($size, $column) as $chunk) {
            $callback($chunk);
        }
    }

    /**
     * Lazily yield every matching model, fetching one chunk at a time under the
     * hood. Constant memory — the safe way to iterate a large table.
     *
     * @return \Generator<int,Model>
     */
    public function lazyById(int $size = 1000, ?string $column = null): \Generator
    {
        foreach ($this->keysetChunks($size, $column) as $chunk) {
            foreach ($chunk as $model) {
                yield $model;
            }
        }
    }

    /**
     * @return \Generator<int,array<int,Model>>
     */
    protected function keysetChunks(int $size, ?string $column): \Generator
    {
        if ($size < 1) {
            throw new \InvalidArgumentException('Chunk size must be at least 1.');
        }

        $column ??= $this->model->getKeyName();
        $lastId = null;
        $first = true;

        do {
            $query = clone $this->query;   // fresh copy of the base constraints
            $query->orders = [];
            $query->offset = null;

            if (!$first) {
                $query->where($column, '>', $lastId);
            }
            $query->orderBy($column)->limit($size);

            $models = $this->hydrateAndLoad($query->get());
            $count = count($models);

            if ($count > 0) {
                $lastId = end($models)->getAttribute($column);
                yield $models;
            }

            $first = false;
        } while ($count === $size);
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,Model>
     */
    public function hydrate(array $rows): array
    {
        return array_map(
            fn (array $row): Model => $this->model->newFromBuilder($row),
            $rows
        );
    }

    /**
     * @param array<string,mixed> $values
     * @return array<string,mixed>
     */
    protected function addUpdatedAtColumn(array $values): array
    {
        if ($this->model->usesTimestamps()) {
            $values[$this->model::UPDATED_AT] ??= $this->model->freshTimestampString();
        }

        return $values;
    }

    /**
     * @param array<int,Model> $models
     * @return array<int,Model>
     */
    protected function eagerLoadRelations(array $models): array
    {
        foreach ($this->eagerLoad as $name) {
            $models = $this->eagerLoadRelation($models, $name);
        }

        return $models;
    }

    /**
     * @param array<int,Model> $models
     * @return array<int,Model>
     */
    protected function eagerLoadRelation(array $models, string $name): array
    {
        $relation = $this->getRelation($name);

        $models = $relation->initRelation($models, $name);
        $relation->addEagerConstraints($models);

        return $relation->match($models, $relation->getEager(), $name);
    }

    protected function getRelation(string $name): Relation
    {
        /** @var Relation $relation */
        $relation = $this->model->newInstance()->{$name}();

        return $relation;
    }

    /**
     * Forward any other call to the underlying query builder, returning $this
     * for fluent (builder-returning) calls and the raw result otherwise.
     *
     * @param array<int,mixed> $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        $result = $this->query->{$method}(...$parameters);

        return $result instanceof QueryBuilder ? $this : $result;
    }
}
