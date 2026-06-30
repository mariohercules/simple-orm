<?php

declare(strict_types=1);

namespace SimpleORM\Tools;

use SimpleORM\Connection\Connection;
use SimpleORM\Support\Str;

/**
 * Generates model classes by introspecting INFORMATION_SCHEMA, so $fillable,
 * $primaryKey, $casts and $timestamps reflect the real table — no hardcoded
 * column lists. Introspection methods are protected so they can be stubbed.
 */
class ModelGenerator
{
    public function __construct(
        protected Connection $connection,
        protected string $database,
        protected string $outputDir,
        protected string $namespace = 'App\\Models',
        protected string $stripPrefix = ''
    ) {
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
    }

    /**
     * @return list<string> Absolute paths of the files written.
     */
    public function generateAll(): array
    {
        return array_map([$this, 'generate'], $this->tables());
    }

    public function generate(string $table): string
    {
        $columns = $this->columns($table);
        $names = array_map(static fn (array $c): string => $c['name'], $columns);

        $primaryKey = $this->primaryKey($table) ?? 'id';
        $timestamps = in_array('created_at', $names, true) && in_array('updated_at', $names, true);

        $reserved = array_filter([$primaryKey, 'created_at', 'updated_at']);
        $fillable = array_values(array_diff($names, $reserved));

        $casts = [];
        foreach ($columns as $column) {
            if ($column['name'] === $primaryKey) {
                continue;
            }
            $cast = $this->castFor($column['type']);
            if ($cast !== null) {
                $casts[$column['name']] = $cast;
            }
        }

        $className = $this->className($table);
        $path = rtrim($this->outputDir, '/\\') . DIRECTORY_SEPARATOR . $className . '.php';

        file_put_contents($path, $this->render($className, $table, $primaryKey, $fillable, $casts, $timestamps));

        return $path;
    }

    /**
     * @return list<string>
     */
    public function tables(): array
    {
        $rows = $this->connection->select(
            'select table_name as name from information_schema.tables where table_schema = ? order by table_name',
            [$this->database]
        );

        return array_map(static fn (array $r): string => (string) $r['name'], $rows);
    }

    /**
     * @return list<array{name:string,type:string}>
     */
    protected function columns(string $table): array
    {
        $rows = $this->connection->select(
            'select column_name as name, data_type as type from information_schema.columns
             where table_schema = ? and table_name = ? order by ordinal_position',
            [$this->database, $table]
        );

        return array_map(
            static fn (array $r): array => ['name' => (string) $r['name'], 'type' => (string) $r['type']],
            $rows
        );
    }

    protected function primaryKey(string $table): ?string
    {
        $rows = $this->connection->select(
            "select column_name as name from information_schema.key_column_usage
             where table_schema = ? and table_name = ? and constraint_name = 'PRIMARY'
             order by ordinal_position limit 1",
            [$this->database, $table]
        );

        return isset($rows[0]['name']) ? (string) $rows[0]['name'] : null;
    }

    protected function castFor(string $type): ?string
    {
        return match (strtolower($type)) {
            'tinyint' => 'boolean',
            'smallint', 'mediumint', 'int', 'integer', 'bigint' => 'integer',
            'decimal', 'float', 'double' => 'float',
            'json' => 'array',
            default => null,
        };
    }

    protected function className(string $table): string
    {
        $name = $table;

        if ($this->stripPrefix !== '' && str_starts_with($name, $this->stripPrefix)) {
            $name = substr($name, strlen($this->stripPrefix));
        }

        return Str::studly(Str::singular($name));
    }

    /**
     * @param list<string> $fillable
     * @param array<string,string> $casts
     */
    protected function render(
        string $className,
        string $table,
        string $primaryKey,
        array $fillable,
        array $casts,
        bool $timestamps
    ): string {
        $fillableBlock = $this->phpList($fillable);
        $castsBlock = $this->phpMap($casts);
        $timestampsLine = $timestamps ? 'true' : 'false';

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$this->namespace};

        use SimpleORM\\Model\\Model;

        class {$className} extends Model
        {
            protected string \$table = '{$table}';

            protected string \$primaryKey = '{$primaryKey}';

            /** @var array<int,string> */
            protected array \$fillable = [{$fillableBlock}];

            /** @var array<int,string> */
            protected array \$guarded = ['{$primaryKey}'];

            /** @var array<string,string> */
            protected array \$casts = [{$castsBlock}];

            public bool \$timestamps = {$timestampsLine};
        }

        PHP;
    }

    /**
     * @param list<string> $items
     */
    private function phpList(array $items): string
    {
        if ($items === []) {
            return '';
        }

        $lines = array_map(static fn (string $i): string => "        '" . addcslashes($i, "'\\") . "',", $items);

        return "\n" . implode("\n", $lines) . "\n    ";
    }

    /**
     * @param array<string,string> $map
     */
    private function phpMap(array $map): string
    {
        if ($map === []) {
            return '';
        }

        $lines = [];
        foreach ($map as $key => $value) {
            $lines[] = "        '" . addcslashes($key, "'\\") . "' => '{$value}',";
        }

        return "\n" . implode("\n", $lines) . "\n    ";
    }
}
