<?php

declare(strict_types=1);

namespace SimpleORM\Connection;

use SimpleORM\Exceptions\ConnectionException;

/**
 * Holds named connection configurations and resolves them lazily into
 * Connection instances. Replaces the old global DatabaseConnection singleton.
 */
class ConnectionManager
{
    /** @var array<string,array<string,mixed>> */
    private array $configs = [];

    /** @var array<string,Connection> */
    private array $connections = [];

    private string $default = 'default';

    /**
     * @param array<string,mixed> $config
     */
    public function addConnection(array $config, string $name = 'default'): void
    {
        $this->configs[$name] = $config;
        unset($this->connections[$name]);
    }

    public function setDefaultConnection(string $name): void
    {
        $this->default = $name;
    }

    public function getDefaultConnection(): string
    {
        return $this->default;
    }

    public function connection(?string $name = null): Connection
    {
        $name ??= $this->default;

        if (!isset($this->connections[$name])) {
            if (!isset($this->configs[$name])) {
                throw new ConnectionException("Connection [{$name}] is not configured.");
            }

            $this->connections[$name] = new Connection($this->configs[$name]);
        }

        return $this->connections[$name];
    }

    public function purge(?string $name = null): void
    {
        $name ??= $this->default;

        if (isset($this->connections[$name])) {
            $this->connections[$name]->disconnect();
            unset($this->connections[$name]);
        }
    }
}
