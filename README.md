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

## Model Generator

The ORM includes a Model Generator tool to automatically create model classes for your database tables.

### Using the Model Generator

1. Create the tools directory in your project root:
```bash
mkdir -p tools
```

2. Create the ModelGenerator file (tools/ModelGenerator.php):
```php
<?php

class ModelGenerator
{
    private string $modelDirectory;
    private string $namespace = 'App\\Models';

    public function __construct()
    {
        // Get the project root directory
        $rootDir = dirname(__DIR__);
        $this->modelDirectory = $rootDir . '/app/Models/';

        // Create Models directory if it doesn't exist
        if (!is_dir($this->modelDirectory)) {
            mkdir($this->modelDirectory, 0755, true);
        }
    }

    public function generate(string $tableName)
    {
        // Remove 'ngb_' prefix and convert to CamelCase
        $className = $this->getClassName($tableName);
        
        $template = <<<PHP
<?php

namespace {$this->namespace};

use SimpleORM\Model;

class {$className} extends Model
{
    protected string \$table = '{$tableName}';
    
    protected array \$fillable = [
        // Add your fillable fields here
    ];
    
    protected array \$guarded = ['id'];
}
PHP;

        $filename = $this->modelDirectory . $className . '.php';
        file_put_contents($filename, $template);
        
        echo "Generated model {$className} for table {$tableName}\n";
    }

    private function getClassName(string $tableName): string
    {
        // Remove prefix (e.g., 'ngb_')
        $name = str_replace('ngb_', '', $tableName);
        
        // Convert to singular if possible
        if (substr($name, -1) === 's') {
            $name = substr($name, 0, -1);
        }
        
        // Convert snake_case to CamelCase
        return str_replace('_', '', ucwords($name, '_'));
    }
}
```

3. Create a generator script (tools/generate_models.php):
```php
<?php

require_once __DIR__ . '/ModelGenerator.php';

// List your tables
$tables = [
    'users',
    'posts',
    'comments',
    // Add all your tables here
];

// Create generator instance
$generator = new ModelGenerator();

// Generate models for each table
foreach ($tables as $table) {
    $generator->generate($table);
}

echo "\nAll models have been generated successfully!\n";
```

### Running the Generator

1. From your project root:
```bash
php tools/generate_models.php
```

### Generated Model Structure

The generator will:
- Create model classes in app/Models/
- Remove the 'ngb_' prefix from table names
- Convert snake_case to CamelCase
- Set up proper namespacing
- Include basic model configuration

Example output for table 'ngb_users':
```php
<?php

namespace App\Models;

use SimpleORM\Model;

class User extends Model
{
    protected string $table = 'ngb_users';
    
    protected array $fillable = [
        // Add your fillable fields here
    ];
    
    protected array $guarded = ['id'];
}
```

### Customizing the Generator

You can modify the ModelGenerator class to:
- Change the namespace
- Add custom methods
- Modify the model template
- Add relationship methods
- Include additional properties

Example of customizing the template:
```php
private function getTemplate($className, $tableName): string
{
    return <<<PHP
<?php

namespace {$this->namespace};

use SimpleORM\Model;

class {$className} extends Model
{
    protected string \$table = '{$tableName}';
    
    protected array \$fillable = [
        // Add your fillable fields here
    ];
    
    protected array \$guarded = ['id'];
    
    // Add custom methods
    public function getByStatus(string \$status): array
    {
        return self::query()
            ->where('status', '=', \$status)
            ->get();
    }
}
PHP;
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