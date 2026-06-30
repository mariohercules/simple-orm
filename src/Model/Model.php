<?php

declare(strict_types=1);

namespace SimpleORM\Model;

use Closure;
use JsonSerializable;
use RuntimeException;
use SimpleORM\Connection\Connection;
use SimpleORM\Connection\ConnectionManager;
use SimpleORM\Model\Concerns\HasAttributes;
use SimpleORM\Model\Concerns\HasEvents;
use SimpleORM\Model\Concerns\HasGlobalScopes;
use SimpleORM\Model\Concerns\HasRelationships;
use SimpleORM\Model\Concerns\HasTimestamps;
use SimpleORM\Query\QueryBuilder;
use SimpleORM\Support\Str;

/**
 * Active Record base. Persistence is delegated entirely to the query builder
 * (no inline SQL), mass assignment honours $fillable/$guarded, and saves write
 * only the dirty columns.
 *
 * Boot once before use:
 *   (new SimpleORM\Manager())->addConnection($config)->setAsGlobal()->bootModels();
 *
 * @method static Builder where(string $column, ?string $operator = null, mixed $value = null)
 * @method static Builder whereIn(string $column, array $values)
 * @method static Builder orderBy(string $column, string $direction = 'asc')
 * @method static Builder with(array|string $relations)
 * @method static Builder limit(int $value)
 */
abstract class Model implements JsonSerializable
{
    use HasAttributes;
    use HasTimestamps;
    use HasRelationships;
    use HasEvents;
    use HasGlobalScopes;

    protected static ?ConnectionManager $resolver = null;

    /** @var array<class-string,bool> */
    protected static array $booted = [];

    protected ?string $connection = null;
    protected string $table = '';
    protected string $primaryKey = 'id';
    protected string $keyType = 'int';
    public bool $incrementing = true;

    /** @var array<int,string> */
    protected array $fillable = [];

    /** @var array<int,string> */
    protected array $guarded = ['id'];

    /** @var array<int,string> */
    protected array $hidden = [];

    public bool $exists = false;

    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    /**
     * @param array<string,mixed> $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->bootIfNotBooted();
        $this->syncOriginal();
        $this->fill($attributes);
    }

    /**
     * Boot the model the first time it is instantiated for a given class: runs
     * trait booters (boot{TraitName}, e.g. bootSoftDeletes) and the booted() hook.
     */
    protected function bootIfNotBooted(): void
    {
        if (!isset(static::$booted[static::class])) {
            static::$booted[static::class] = true;
            static::boot();
            static::booted();
        }
    }

    protected static function boot(): void
    {
        foreach (class_uses_recursive(static::class) as $trait) {
            $method = 'boot' . class_basename($trait);

            if (method_exists(static::class, $method)) {
                forward_static_call([static::class, $method]);
            }
        }
    }

    /**
     * User hook to register events / scopes once per class. Override in a model.
     */
    protected static function booted(): void
    {
    }

    public static function setConnectionResolver(ConnectionManager $resolver): void
    {
        static::$resolver = $resolver;
    }

    public static function getConnectionResolver(): ?ConnectionManager
    {
        return static::$resolver;
    }

    public function getConnection(): Connection
    {
        if (static::$resolver === null) {
            throw new RuntimeException(
                'No connection resolver set. Boot SimpleORM\\Manager (->bootModels()) first.'
            );
        }

        return static::$resolver->connection($this->connection);
    }

    public function getTable(): string
    {
        return $this->table !== ''
            ? $this->table
            : Str::snake(Str::plural(class_basename($this)));
    }

    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    public function getKey(): mixed
    {
        return $this->getAttribute($this->primaryKey);
    }

    public function getKeyType(): string
    {
        return $this->keyType;
    }

    public function getForeignKey(): string
    {
        return Str::snake(class_basename($this)) . '_' . $this->primaryKey;
    }

    public function newQueryBuilder(): QueryBuilder
    {
        return (new QueryBuilder($this->getConnection()))->from($this->getTable());
    }

    public function newQuery(): Builder
    {
        return (new Builder($this->newQueryBuilder()))
            ->setModel($this)
            ->withGlobalScopes($this->getGlobalScopes());
    }

    public static function query(): Builder
    {
        return (new static())->newQuery();
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function newInstance(array $attributes = []): static
    {
        $model = new static($attributes);
        $model->connection = $this->connection;

        return $model;
    }

    /**
     * Build a model from an existing DB row: raw attributes, marked as persisted.
     *
     * @param array<string,mixed> $attributes
     */
    public function newFromBuilder(array $attributes): static
    {
        $model = $this->newInstance();
        $model->setRawAttributes($attributes, true);
        $model->exists = true;
        $model->fireModelEvent('retrieved', false);

        return $model;
    }

    public static function find(int|string $id): ?static
    {
        return static::query()->find($id);
    }

    public static function findOrFail(int|string $id): static
    {
        return static::query()->findOrFail($id);
    }

    /**
     * @param array<int,string>|string $columns
     * @return array<int,static>
     */
    public static function all(array|string $columns = ['*']): array
    {
        return static::query()->get($columns);
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public static function create(array $attributes): static
    {
        return static::query()->create($attributes);
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }

        return $this;
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function forceFill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this;
    }

    public function isFillable(string $key): bool
    {
        if (in_array($key, $this->fillable, true)) {
            return true;
        }

        // With an explicit whitelist, anything not listed is not fillable.
        if ($this->fillable !== []) {
            return false;
        }

        return !in_array($key, $this->guarded, true) && !str_starts_with($key, '_');
    }

    public function save(): bool
    {
        if ($this->fireModelEvent('saving') === false) {
            return false;
        }

        $saved = $this->exists ? $this->performUpdate() : $this->performInsert();

        if ($saved) {
            $this->fireModelEvent('saved', false);
        }

        return $saved;
    }

    protected function performUpdate(): bool
    {
        if (!$this->isDirty()) {
            return true;
        }

        if ($this->fireModelEvent('updating') === false) {
            return false;
        }

        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }

        $dirty = $this->getDirty();

        if ($dirty !== []) {
            $this->newQueryBuilder()
                ->where($this->primaryKey, '=', $this->getKey())
                ->update($dirty);
        }

        $this->syncOriginal();
        $this->fireModelEvent('updated', false);

        return true;
    }

    protected function performInsert(): bool
    {
        if ($this->fireModelEvent('creating') === false) {
            return false;
        }

        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }

        $query = $this->newQueryBuilder();

        if ($this->incrementing) {
            $id = $query->insertGetId($this->attributes);
            $this->setAttribute($this->primaryKey, $this->castKeyValue($id));
        } else {
            $query->insert($this->attributes);
        }

        $this->exists = true;
        $this->syncOriginal();
        $this->fireModelEvent('created', false);

        return true;
    }

    protected function castKeyValue(string $id): int|string
    {
        return $this->keyType === 'int' ? (int) $id : $id;
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function update(array $attributes): bool
    {
        if (!$this->exists) {
            return false;
        }

        $this->fill($attributes);

        return $this->save();
    }

    public function delete(): bool
    {
        if (!$this->exists || $this->getKey() === null) {
            return false;
        }

        if ($this->fireModelEvent('deleting') === false) {
            return false;
        }

        $this->performDeleteOnModel();
        $this->fireModelEvent('deleted', false);

        return true;
    }

    /**
     * The actual delete. Overridden by the SoftDeletes trait to stamp deleted_at
     * instead of removing the row.
     */
    protected function performDeleteOnModel(): void
    {
        $this->newQueryBuilder()
            ->where($this->primaryKey, '=', $this->getKey())
            ->delete();

        $this->exists = false;
    }

    public function refresh(): static
    {
        if (!$this->exists) {
            return $this;
        }

        $fresh = static::query()->find($this->getKey());

        if ($fresh !== null) {
            $this->setRawAttributes($fresh->getAttributes(), true);
            $this->relations = [];
        }

        return $this;
    }

    /**
     * @template T
     * @param Closure(Connection):T $callback
     * @return T
     */
    public static function transaction(Closure $callback): mixed
    {
        return (new static())->getConnection()->transaction($callback);
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        $result = [];

        foreach ($this->attributes as $key => $value) {
            if (!in_array($key, $this->hidden, true)) {
                $result[$key] = $this->getAttribute($key);
            }
        }

        foreach ($this->relations as $key => $value) {
            if (!in_array($key, $this->hidden, true)) {
                $result[$key] = $this->relationToArray($value);
            }
        }

        return $result;
    }

    protected function relationToArray(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(
                static fn ($item) => $item instanceof Model ? $item->toArray() : $item,
                $value
            );
        }

        return $value instanceof Model ? $value->toArray() : $value;
    }

    public function toJson(int $flags = 0): string
    {
        return (string) json_encode($this->toArray(), $flags);
    }

    /**
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]) || $this->relationLoaded($key);
    }

    public function __unset(string $key): void
    {
        unset($this->attributes[$key], $this->relations[$key]);
    }

    /**
     * @param array<int,mixed> $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->newQuery()->{$method}(...$parameters);
    }

    /**
     * @param array<int,mixed> $parameters
     */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        return (new static())->{$method}(...$parameters);
    }

    public function __toString(): string
    {
        return $this->toJson();
    }
}
