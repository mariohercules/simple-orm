# Simple ORM

A lightweight, Eloquent-flavoured Active Record ORM for PHP 8.1+ on PDO/MySQL.
Models, a fluent query builder for all four verbs, relationships with eager
loading, attribute casting, dirty-tracked saves, and a schema-introspecting
model generator — in a few hundred lines, with no framework.

## Requirements

- PHP 8.1+ with `pdo` (and `pdo_mysql` for MySQL)
- Composer

## Installation

```bash
composer install
cp .env.example .env   # then edit credentials
```

```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=your_database
DB_USER=root
DB_PASS=secret
```

## Bootstrapping

There is no implicit/global connection — wire one up once at startup. The
`Manager` registers connections and teaches the `Model` base how to resolve them.

```php
use SimpleORM\Manager;

$manager = (new Manager())
    ->addConnection(require __DIR__ . '/config/database.php')
    ->setAsGlobal()   // enables Manager::table() for raw queries
    ->bootModels();   // models can now resolve their connection
```

`config/database.php` reads the `.env` values and returns a plain config array,
so `addConnection(require '...')` is all you need. Multiple databases:

```php
$manager->addConnection($analyticsConfig, 'analytics');
// then on a model:  protected ?string $connection = 'analytics';
```

## Defining models

```php
use SimpleORM\Model\Model;

class User extends Model
{
    // Table is inferred (User -> users); override when needed.
    protected array $fillable = ['name', 'email', 'active'];
    protected array $casts    = ['active' => 'boolean'];
    protected array $hidden   = ['password'];   // excluded from toArray()/JSON

    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}
```

Casts: `int`, `float`, `string`, `bool`, `array`/`json`, `object`.

## CRUD

```php
$user = User::create(['name' => 'Alice', 'email' => 'alice@example.com']);

$user = User::find(1);            // ?User
$user = User::findOrFail(1);      // throws ModelNotFoundException
$all  = User::all();             // User[]

$user->name = 'Alice Smith';
$user->save();                    // writes only the changed columns
$user->update(['active' => false]);

$user->delete();
```

`save()` is dirty-aware: an update only writes columns that actually changed,
and `created_at` / `updated_at` are maintained automatically (set
`public bool $timestamps = false;` to opt out).

## Query builder

`Model::query()` returns a builder that hydrates rows into models. The full
fluent surface is available, and writes go through the same builder.

```php
$users = User::query()
    ->select(['users.*', 'roles.name as role_name'])
    ->join('roles', 'users.role_id', '=', 'roles.id')
    ->where('users.active', 1)            // 2-arg shorthand for '='
    ->whereIn('users.role_id', [1, 2, 3])
    ->orderBy('users.created_at', 'desc')
    ->limit(10)
    ->get();

User::where('active', 1)->count();
User::where('active', 1)->exists();
User::query()->forPage(2, 15)->get();      // pagination
User::query()->where('active', 0)->update(['active' => 1]);  // bulk update
User::where('spam', 1)->delete();          // bulk delete
```

Operators are whitelisted and order directions validated, so untrusted input
can't smuggle SQL through a column/operator slot. For raw, model-less queries:

```php
$rows = SimpleORM\Manager::table('users')->where('active', 1)->get();  // arrays
```

## Relationships

```php
class User extends Model
{
    public function posts()   { return $this->hasMany(Post::class); }
    public function profile() { return $this->hasOne(Profile::class); }
}

class Post extends Model
{
    public function user() { return $this->belongsTo(User::class); }
}
```

Lazy access runs a query on first touch and caches the result:

```php
$user = User::find(1);
$user->posts;        // Post[]  (single query, cached)
$post->user;         // User
```

Eager loading avoids N+1 by resolving a relation for the whole result set in one
extra query:

```php
$users = User::with('posts')->get();   // 2 queries total, not 1 + N
foreach ($users as $user) {
    foreach ($user->posts as $post) { /* ... */ }
}
```

## Transactions

```php
User::transaction(function () {
    $user = User::create([...]);
    $user->profile()->getQuery()->create([...]);
});   // commits on return, rolls back on any exception
```

## Large tables

`all()` / `get()` materialise the whole result set and build one model per row —
fine for small results, ruinous for big ones. For large tables use the helpers
below (measured on 200k rows, MySQL 5.7):

```php
// Stream in keyset-ordered chunks — constant memory (~6 MB vs ~170 MB for all()):
User::query()->chunkById(1000, function (array $users) {
    foreach ($users as $user) { /* ... */ }
});

// Or iterate lazily as a generator (same engine, constant memory):
foreach (User::where('active', 1)->lazyById() as $user) {
    /* ... */
}

// Keyset pagination instead of OFFSET — ~13x faster on deep pages:
$page = User::query()->whereKeyAfter($lastSeenId)->limit(50)->get();

// exists() stops at the first row (LIMIT 1) instead of count(*) — ~11x faster:
if (User::where('email', $email)->exists()) { /* ... */ }
```

`chunkById`/`lazyById` page by the primary key (`where id > ? order by id limit n`),
so they hold one chunk in memory at a time and stay correct even while rows are
being inserted — unlike `OFFSET`-based paging.

## Model generator

Generate model classes by introspecting the live database — `$fillable`,
`$primaryKey`, `$casts`, and `$timestamps` come from the real schema:

```bash
# All tables, stripping an "ngb_" prefix from class names:
bin/simple-orm generate --prefix=ngb_ --namespace="App\Models" --out=app/Models

# Specific tables:
bin/simple-orm generate users posts
```

## Testing

```bash
composer test       # PHPUnit
composer analyse    # PHPStan (level 5)
```

Unit tests (`tests/Query`, `tests/Model`) need no database. The integration
suite runs the full ORM against in-memory SQLite and skips automatically if
`pdo_sqlite` is unavailable.

## License

MIT.
