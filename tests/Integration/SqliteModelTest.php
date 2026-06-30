<?php

declare(strict_types=1);

namespace SimpleORM\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SimpleORM\Exceptions\ModelNotFoundException;
use SimpleORM\Manager;
use SimpleORM\Model\Model;

class IntegrationUser extends Model
{
    protected string $table = 'users';
    protected array $fillable = ['name', 'email', 'active'];
    protected array $casts = ['active' => 'boolean'];
    protected array $hidden = ['email'];

    public function posts(): \SimpleORM\Relations\HasMany
    {
        return $this->hasMany(IntegrationPost::class, 'user_id');
    }
}

class IntegrationPost extends Model
{
    protected string $table = 'posts';
    protected array $fillable = ['user_id', 'title', 'meta'];
    protected array $casts = ['meta' => 'array', 'user_id' => 'integer'];

    public function user(): \SimpleORM\Relations\BelongsTo
    {
        return $this->belongsTo(IntegrationUser::class, 'user_id');
    }
}

/**
 * Full-stack CRUD + relationship coverage against in-memory SQLite.
 */
final class SqliteModelTest extends TestCase
{
    private Manager $manager;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is not available.');
        }

        $this->manager = (new Manager())
            ->addConnection(['dsn' => 'sqlite::memory:'])
            ->setAsGlobal()
            ->bootModels();

        $pdo = $this->manager->getConnection()->getPdo();
        $pdo->exec('create table users (id integer primary key autoincrement, name text, email text, active integer, created_at text, updated_at text)');
        $pdo->exec('create table posts (id integer primary key autoincrement, user_id integer, title text, meta text, created_at text, updated_at text)');
    }

    public function testCreateAssignsIdAndIgnoresGuarded(): void
    {
        $user = IntegrationUser::create(['name' => 'Alice', 'email' => 'a@x.com', 'active' => true, 'id' => 999]);

        self::assertSame(1, $user->id);
        self::assertTrue($user->exists);
        self::assertTrue($user->active);
    }

    public function testFindHydratesModel(): void
    {
        IntegrationUser::create(['name' => 'Alice', 'email' => 'a@x.com', 'active' => true]);

        $found = IntegrationUser::find(1);
        self::assertInstanceOf(IntegrationUser::class, $found);
        self::assertSame('Alice', $found->name);
    }

    public function testFindOrFailThrows(): void
    {
        $this->expectException(ModelNotFoundException::class);

        IntegrationUser::findOrFail(123);
    }

    public function testPartialUpdateWritesOnlyDirtyColumns(): void
    {
        $user = IntegrationUser::create(['name' => 'Alice', 'email' => 'a@x.com', 'active' => true]);
        $user->name = 'Alice Smith';

        self::assertTrue($user->isDirty('name'));
        self::assertFalse($user->isDirty('email'));
        $user->save();

        self::assertSame('Alice Smith', IntegrationUser::find(1)->name);
    }

    public function testAggregatesAndWhere(): void
    {
        IntegrationUser::create(['name' => 'Alice', 'email' => 'a@x.com', 'active' => true]);
        IntegrationPost::create(['user_id' => 1, 'title' => 'A', 'meta' => []]);
        IntegrationPost::create(['user_id' => 1, 'title' => 'B', 'meta' => []]);

        self::assertSame(2, IntegrationPost::query()->count());
        self::assertSame(2, IntegrationPost::where('user_id', 1)->count());
        self::assertTrue(IntegrationPost::where('user_id', 1)->exists());
    }

    public function testJsonCastRoundTrip(): void
    {
        IntegrationPost::create(['user_id' => 1, 'title' => 'A', 'meta' => ['tags' => ['x']]]);

        self::assertSame(['tags' => ['x']], IntegrationPost::find(1)->meta);
    }

    public function testLazyRelationships(): void
    {
        IntegrationUser::create(['name' => 'Alice', 'email' => 'a@x.com', 'active' => true]);
        IntegrationPost::create(['user_id' => 1, 'title' => 'A', 'meta' => []]);
        IntegrationPost::create(['user_id' => 1, 'title' => 'B', 'meta' => []]);

        $user = IntegrationUser::find(1);
        self::assertCount(2, $user->posts);
        self::assertInstanceOf(IntegrationPost::class, $user->posts[0]);

        $post = IntegrationPost::find(1);
        self::assertInstanceOf(IntegrationUser::class, $post->user);
        self::assertSame(1, $post->user->id);
    }

    public function testEagerLoadingMatchesRelations(): void
    {
        IntegrationUser::create(['name' => 'Alice', 'email' => 'a@x.com', 'active' => true]);
        IntegrationUser::create(['name' => 'Bob', 'email' => 'b@x.com', 'active' => true]);
        IntegrationPost::create(['user_id' => 1, 'title' => 'A', 'meta' => []]);
        IntegrationPost::create(['user_id' => 1, 'title' => 'B', 'meta' => []]);
        IntegrationPost::create(['user_id' => 2, 'title' => 'C', 'meta' => []]);

        $users = IntegrationUser::with('posts')->get();

        self::assertTrue($users[0]->relationLoaded('posts'));
        self::assertCount(2, $users[0]->getRelation('posts'));
        self::assertCount(1, $users[1]->getRelation('posts'));
    }

    public function testTransactionRollback(): void
    {
        IntegrationUser::create(['name' => 'Alice', 'email' => 'a@x.com', 'active' => true]);

        try {
            IntegrationUser::transaction(function (): void {
                IntegrationUser::create(['name' => 'Temp', 'email' => 't@x.com', 'active' => true]);
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
            // expected
        }

        self::assertSame(1, IntegrationUser::query()->count());
    }

    public function testDelete(): void
    {
        $user = IntegrationUser::create(['name' => 'Alice', 'email' => 'a@x.com', 'active' => true]);

        self::assertTrue($user->delete());
        self::assertSame(0, IntegrationUser::query()->count());
    }

    public function testHiddenFieldNotSerialized(): void
    {
        IntegrationUser::create(['name' => 'Alice', 'email' => 'a@x.com', 'active' => true]);

        $array = IntegrationUser::find(1)->toArray();
        self::assertArrayNotHasKey('email', $array);
        self::assertSame('Alice', $array['name']);
    }
}
