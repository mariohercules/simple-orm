<?php

declare(strict_types=1);

namespace SimpleORM\Connection;

use Closure;
use PDO;
use PDOException;
use SimpleORM\Exceptions\ConnectionException;
use SimpleORM\Exceptions\QueryException;

/**
 * Wraps a single PDO connection. The PDO handle is created lazily on first use
 * so that constructing a model or builder never opens a socket by itself.
 */
class Connection
{
    private ?PDO $pdo = null;

    private const DEFAULT_OPTIONS = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    /**
     * @param array<string,mixed> $config
     */
    public function __construct(
        private array $config = [],
        ?PDO $pdo = null
    ) {
        $this->pdo = $pdo;
    }

    public function getPdo(): PDO
    {
        return $this->pdo ??= $this->createPdo();
    }

    private function createPdo(): PDO
    {
        $dsn = $this->config['dsn'] ?? $this->buildDsn();

        try {
            return new PDO(
                $dsn,
                $this->config['user'] ?? $this->config['username'] ?? null,
                $this->config['password'] ?? $this->config['pass'] ?? null,
                ($this->config['options'] ?? []) + self::DEFAULT_OPTIONS,
            );
        } catch (PDOException $e) {
            throw new ConnectionException('Database connection failed: ' . $e->getMessage(), 0, $e);
        }
    }

    private function buildDsn(): string
    {
        $driver = $this->config['driver'] ?? 'mysql';

        return sprintf(
            '%s:host=%s;port=%s;dbname=%s;charset=%s',
            $driver,
            $this->config['host'] ?? 'localhost',
            $this->config['port'] ?? '3306',
            $this->config['name'] ?? $this->config['database'] ?? '',
            $this->config['charset'] ?? 'utf8mb4',
        );
    }

    /**
     * @param array<int,mixed> $bindings
     * @return array<int,array<string,mixed>>
     */
    public function select(string $sql, array $bindings = []): array
    {
        return $this->run($sql, $bindings, function (string $sql, array $bindings): array {
            $statement = $this->getPdo()->prepare($sql);
            $statement->execute($bindings);

            /** @var array<int,array<string,mixed>> $rows */
            $rows = $statement->fetchAll();

            return $rows;
        });
    }

    /**
     * @param array<int,mixed> $bindings
     */
    public function insert(string $sql, array $bindings = []): bool
    {
        return $this->statement($sql, $bindings);
    }

    /**
     * @param array<int,mixed> $bindings
     */
    public function statement(string $sql, array $bindings = []): bool
    {
        return $this->run($sql, $bindings, function (string $sql, array $bindings): bool {
            return $this->getPdo()->prepare($sql)->execute($bindings);
        });
    }

    /**
     * Run a write and return the number of affected rows.
     *
     * @param array<int,mixed> $bindings
     */
    public function affectingStatement(string $sql, array $bindings = []): int
    {
        return $this->run($sql, $bindings, function (string $sql, array $bindings): int {
            $statement = $this->getPdo()->prepare($sql);
            $statement->execute($bindings);

            return $statement->rowCount();
        });
    }

    /**
     * @param array<int,mixed> $bindings
     */
    public function update(string $sql, array $bindings = []): int
    {
        return $this->affectingStatement($sql, $bindings);
    }

    /**
     * @param array<int,mixed> $bindings
     */
    public function delete(string $sql, array $bindings = []): int
    {
        return $this->affectingStatement($sql, $bindings);
    }

    public function lastInsertId(): string
    {
        return (string) $this->getPdo()->lastInsertId();
    }

    /**
     * Execute the callback within a transaction, rolling back on any throwable.
     *
     * @template T
     * @param Closure(self):T $callback
     * @return T
     */
    public function transaction(Closure $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function beginTransaction(): void
    {
        $this->getPdo()->beginTransaction();
    }

    public function commit(): void
    {
        $this->getPdo()->commit();
    }

    public function rollBack(): void
    {
        $this->getPdo()->rollBack();
    }

    public function disconnect(): void
    {
        $this->pdo = null;
    }

    /**
     * @param array<int,mixed> $bindings
     * @param Closure(string,array<int,mixed>):mixed $callback
     */
    private function run(string $sql, array $bindings, Closure $callback): mixed
    {
        try {
            return $callback($sql, $bindings);
        } catch (PDOException $e) {
            throw new QueryException($sql, $bindings, $e);
        }
    }
}
