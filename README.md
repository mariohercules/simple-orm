# Simple ORM Documentation

## Table of Contents
1. [Installation](#installation)
2. [Configuration](#configuration)
3. [Directory Structure](#directory-structure)
4. [Basic Usage](#basic-usage)
5. [Model Creation](#model-creation)
6. [Query Builder](#query-builder)
7. [Database Operations](#database-operations)
8. [Relationships](#relationships)
9. [Examples](#examples)

## Installation

### Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Composer

### Step 1: Clone the Repository
```bash
git clone <your-repository>
cd <project-directory>
```

### Step 2: Install Dependencies
```bash
composer install
```

### Step 3: Configure Environment
Copy the example environment file and modify it with your settings:
```bash
cp .env.example .env
```

## Configuration

### Environment Variables (.env)
```env
DB_HOST=localhost
DB_PORT=8889
DB_NAME=your_database
DB_USER=root
DB_PASS=root
```

### Database Configuration (config/database.php)
The database configuration file automatically loads your environment variables and sets up the connection parameters.

## Directory Structure
```
project/
├── .env
├── composer.json
├── vendor/
├── src/
│   ├── Contracts/
│   │   ├── ConnectionInterface.php
│   │   ├── ModelInterface.php
│   │   └── QueryBuilderInterface.php
│   ├── Database/
│   │   └── DatabaseConnection.php
│   ├── Query/
│   │   └── QueryBuilder.php
│   ├── Traits/
│   │   ├── AttributeHandler.php
│   │   └── TableNameHandler.php
│   └── Model.php
├── app/
│   └── Models/
├── config/
│   └── database.php
└── public/
    └── index.php
```

## Basic Usage

### Creating a Model
```php
<?php

namespace App\Models;

use SimpleORM\Model;

class User extends Model
{
    protected string $table = 'ngb_users';
    
    protected array $fillable = [
        'name',
        'email',
        'password'
    ];
    
    protected array $guarded = ['id'];
}
```

### Basic Operations
```php
// Create
$user = new User();
$user->name = 'John Doe';
$user->email = 'john@example.com';
$user->save();

// Read
$user = User::find(1);

// Update
$user->name = 'Jane Doe';
$user->save();

// Delete
$user->delete();
```

## Query Builder

### Available Methods
- `select(array $columns = ['*'])`
- `where(string $column, string $operator, $value)`
- `orderBy(string $column, string $direction = 'ASC')`
- `limit(int $limit)`
- `join(string $table, string $first, string $operator, string $second)`
- `leftJoin(string $table, string $first, string $operator, string $second)`

### Example Queries
```php
// Complex query
$users = User::query()
    ->select(['users.*', 'roles.name as role_name'])
    ->join('roles', 'users.role_id', '=', 'roles.id')
    ->where('users.active', '=', 1)
    ->orderBy('users.created_at', 'DESC')
    ->limit(10)
    ->get();

// Simple where clause
$activeUsers = User::query()
    ->where('active', '=', 1)
    ->get();
```

## Database Operations

### Connection Management
```php
// Get database instance
$config = require 'config/database.php';
$db = DatabaseConnection::getInstance($config);

// Connect
$connection = $db->connect();

// Disconnect
$db->disconnect();
```

### Transaction Support
```php
$db = DatabaseConnection::getInstance($config);
$connection = $db->connect();

try {
    $connection->beginTransaction();
    
    // Your operations here
    
    $connection->commit();
} catch (Exception $e) {
    $connection->rollBack();
    throw $e;
}
```

## Working with Models

### Defining Relationships
```php
class User extends Model
{
    // One-to-Many relationship example
    public function posts()
    {
        return self::query()
            ->select(['posts.*'])
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->where('users.id', '=', $this->id)
            ->get();
    }
}
```

### Mass Assignment Protection
```php
class User extends Model
{
    // Fields that can be mass assigned
    protected array $fillable = [
        'name',
        'email'
    ];
    
    // Fields that cannot be mass assigned
    protected array $guarded = [
        'id',
        'password'
    ];
}
```

## Examples

### Complete CRUD Example
```php
// Create
$user = new User();
$user->name = 'John Doe';
$user->email = 'john@example.com';
$user->save();

// Read
$user = User::find(1);
$allUsers = User::all();

// Update
$user = User::find(1);
$user->name = 'Jane Doe';
$user->save();

// Delete
$user = User::find(1);
$user->delete();
```

### Working with Relationships
```php
// Get user's posts
$user = User::find(1);
$posts = $user->posts();

// Query with joins
$userPosts = User::query()
    ->select(['users.*', 'posts.title'])
    ->leftJoin('posts', 'users.id', '=', 'posts.user_id')
    ->where('users.active', '=', 1)
    ->get();
```

## Error Handling
The ORM includes built-in error handling and will throw exceptions for:
- Database connection failures
- Query execution errors
- Invalid model operations

Example error handling:
```php
try {
    $user = User::find(1);
    $user->save();
} catch (Exception $e) {
    // Handle the error
    error_log($e->getMessage());
}
```

## Best Practices

1. Always use environment variables for configuration
2. Implement proper error handling
3. Use prepared statements (built into the Query Builder)
4. Define relationships in model classes
5. Use mass assignment protection
6. Keep models in the appropriate namespace

## Contributing

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details.