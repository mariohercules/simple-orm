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
    'host' => $_ENV['HTTP_DB_HOST'],
    'port' => $_ENV['HTTP_DB_PORT'],
    'name' => $_ENV['HTTP_DB_DATABASE'],
    'user' => $_ENV['HTTP_DB_USERNAME'],
    'password' => $_ENV['HTTP_DB_PASSWORD'],
    'options' => [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES => false
    ]
];