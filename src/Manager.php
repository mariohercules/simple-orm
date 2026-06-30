<?php

declare(strict_types=1);

namespace SimpleORM;

use SimpleORM\Connection\Connection;
use SimpleORM\Connection\ConnectionManager;
use SimpleORM\Model\Model;
use SimpleORM\Query\QueryBuilder;

/**
 * Capsule-style entry point. Registers connections and wires them into the
 * Model base so models can resolve their connection statically.
 *
 *   $manager = (new SimpleORM\Manager())
 *       ->addConnection(require __DIR__ . '/config/database.php')
 *       ->setAsGlobal()
 *       ->bootModels();
 *
 *   $rows = SimpleORM\Manager::table('users')->where('active', 1)->get();
 */
class Manager
{
    protected ConnectionManager $connections;

    protected static ?Manager $instance = null;

    public function __construct()
    {
        $this->connections = new ConnectionManager();
    }

    /**
     * @param array<string,mixed> $config
     */
    public function addConnection(array $config, string $name = 'default'): static
    {
        $this->connections->addConnection($config, $name);

        return $this;
    }

    public function getConnection(?string $name = null): Connection
    {
        return $this->connections->connection($name);
    }

    public function getConnectionManager(): ConnectionManager
    {
        return $this->connections;
    }

    public function setAsGlobal(): static
    {
        static::$instance = $this;

        return $this;
    }

    public function bootModels(): static
    {
        Model::setConnectionResolver($this->connections);

        return $this;
    }

    public static function instance(): ?static
    {
        return static::$instance;
    }

    /**
     * Start a raw (non-model) query against a table on the global manager.
     */
    public static function table(string $table, ?string $connection = null): QueryBuilder
    {
        $manager = static::$instance
            ?? throw new \RuntimeException('No global SimpleORM\\Manager set. Call setAsGlobal() first.');

        return (new QueryBuilder($manager->getConnection($connection)))->from($table);
    }
}
