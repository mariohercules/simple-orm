<?php
// config/database.php

// Define the root path
$rootPath = realpath(__DIR__ . '/..');

// Check for composer autoload
if (!file_exists($rootPath . '/vendor/autoload.php')) {
    die('Composer autoload not found. Please run composer install.');
}

require_once $rootPath . '/vendor/autoload.php';

try {
    // Load the .env file
    $dotenv = Dotenv\Dotenv::createImmutable($rootPath);
    $dotenv->safeLoad();
        
} catch (Exception $e) {
    echo "Error loading .env file: " . $e->getMessage() . "\n";
    die();
}

// Return the configuration array
return [
    'driver' => $_ENV['DB_DRIVER'] ?? 'mysql',
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'port' => $_ENV['DB_PORT'] ?? '3306',
    'name' => $_ENV['DB_NAME'] ?? '',
    'user' => $_ENV['DB_USER'] ?? 'root',
    'password' => $_ENV['DB_PASS'] ?? '',
    'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
    'options' => [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES => false
    ]
];