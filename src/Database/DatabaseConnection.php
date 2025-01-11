<?php
namespace SimpleORM\Database;

use SimpleORM\Contracts\ConnectionInterface;
use SimpleORM\Exceptions\ConnectionException;

class DatabaseConnection implements ConnectionInterface
{
    private static ?DatabaseConnection $instance = null;
    private ?\PDO $connection = null;
    private array $config;
    
    private function __construct(array $config)
    {
        $this->config = $config;
    }
    
    public static function getInstance(array $config): self
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }
    
    public function connect(): \PDO
    {
        if ($this->connection === null) {
            try {
                // Build DSN with port
                $dsn = sprintf(
                    "mysql:host=%s;port=%s;dbname=%s",
                    $this->config['host'],
                    $this->config['port'],
                    $this->config['name']
                );
                
                $this->connection = new \PDO(
                    $dsn,
                    $this->config['user'],
                    $this->config['password'],
                    $this->config['options'] ?? [
                        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                        \PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
            } catch (\PDOException $e) {
                throw new ConnectionException("Database connection failed: " . $e->getMessage());
            }
        }
        return $this->connection;
    }
    
    public function disconnect(): void
    {
        $this->connection = null;
        self::$instance = null;
    }
    
    public function getConnection(): ?\PDO
    {
        return $this->connection;
    }
}