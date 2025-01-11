<?php

namespace SimpleORM;

use SimpleORM\Contracts\ModelInterface;
use SimpleORM\Database\DatabaseConnection;
use SimpleORM\Query\QueryBuilder;
use SimpleORM\Traits\AttributeHandler;
use SimpleORM\Traits\TableNameHandler;

abstract class Model implements ModelInterface
{
    use AttributeHandler, TableNameHandler;
    
    protected static DatabaseConnection $db;
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    protected array $guarded = ['id'];
    protected array $hidden = [];
    
    public static function setConnection(DatabaseConnection $db): void
    {
        self::$db = $db;
    }
    
    public static function query(): QueryBuilder
    {
        $instance = new static();
        return new QueryBuilder(
            self::$db->connect(),
            $instance->getTableName()
        );
    }
    
    public static function find(int $id): ?self
    {
        $instance = new static();
        $result = self::query()
            ->where($instance->primaryKey, '=', $id)
            ->limit(1)
            ->get();
            
        if (empty($result)) {
            return null;
        }
        
        $instance->attributes = $result[0];
        return $instance;
    }
    
    public static function all(): array
    {
        $results = self::query()->get();
        return array_map(function($attributes) {
            $instance = new static();
            $instance->attributes = $attributes;
            return $instance;
        }, $results);
    }
    
    public function save(): bool
    {
        if (isset($this->attributes[$this->primaryKey])) {
            return $this->update();
        }
        return $this->insert();
    }
    
    public function delete(): bool
    {
        if (!isset($this->attributes[$this->primaryKey])) {
            return false;
        }
        
        $query = "DELETE FROM {$this->getTableName()} WHERE {$this->primaryKey} = ?";
        $stmt = self::$db->connect()->prepare($query);
        return $stmt->execute([$this->attributes[$this->primaryKey]]);
    }
    
    protected function insert(): bool
    {
        $attributes = array_filter($this->attributes, function($key) {
            return !in_array($key, $this->guarded);
        }, ARRAY_FILTER_USE_KEY);
        
        $columns = array_keys($attributes);
        $values = array_fill(0, count($columns), '?');
        
        $query = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->getTableName(),
            implode(', ', $columns),
            implode(', ', $values)
        );
        
        $stmt = self::$db->connect()->prepare($query);
        $result = $stmt->execute(array_values($attributes));
        
        if ($result) {
            $this->attributes[$this->primaryKey] = self::$db->connect()->lastInsertId();
        }
        
        return $result;
    }
    
    protected function update(): bool
    {
        $attributes = array_filter($this->attributes, function($key) {
            return !in_array($key, $this->guarded) && $key !== $this->primaryKey;
        }, ARRAY_FILTER_USE_KEY);
        
        $setClauses = array_map(function($column) {
            return "$column = ?";
        }, array_keys($attributes));
        
        $query = sprintf(
            "UPDATE %s SET %s WHERE %s = ?",
            $this->getTableName(),
            implode(', ', $setClauses),
            $this->primaryKey
        );
        
        $values = array_values($attributes);
        $values[] = $this->attributes[$this->primaryKey];
        
        $stmt = self::$db->connect()->prepare($query);
        return $stmt->execute($values);
    }

    public function toArray(): array
    {
        return array_diff_key(
            $this->attributes,
            array_flip($this->hidden)
        );
    }
}
