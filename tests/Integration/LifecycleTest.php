<?php

declare(strict_types=1);

namespace SimpleORM\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SimpleORM\Manager;
use SimpleORM\Model\Builder;
use SimpleORM\Model\Model;
use SimpleORM\Model\SoftDeletes;

class EventAccount extends Model
{
    protected string $table = 'accounts';
    protected array $fillable = ['name', 'active'];
    protected array $casts = ['active' => 'boolean'];
    public bool $timestamps = false;

    /** @var array<int,string> */
    public static array $log = [];

    public function scopeActive(Builder $q): Builder
    {
        $q->where('active', 1);

        return $q;
    }
}

class SoftDoc extends Model
{
    use SoftDeletes;

    protected string $table = 'docs';
    protected array $fillable = ['title', 'status'];
    public bool $timestamps = false;

    protected static function booted(): void
    {
        static::addGlobalScope('only_titled', function (Builder $b): void {
            $b->getQuery()->whereNotNull('title');
        });
    }
}

final class LifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is not available.');
        }

        $pdo = (new Manager())
            ->addConnection(['dsn' => 'sqlite::memory:'])
            ->setAsGlobal()
            ->bootModels()
            ->getConnection()
            ->getPdo();

        $pdo->exec('create table accounts (id integer primary key autoincrement, name text, active integer)');
        $pdo->exec('create table docs (id integer primary key autoincrement, title text, status text, deleted_at text)');

        EventAccount::$log = [];
    }

    protected function tearDown(): void
    {
        EventAccount::flushEventListeners();
    }

    public function testEventOrderOnCreateUpdateDelete(): void
    {
        foreach (['saving', 'creating', 'created', 'saved', 'updating', 'updated', 'deleting', 'deleted'] as $event) {
            EventAccount::registerModelEvent($event, static fn () => EventAccount::$log[] = $event);
        }

        $account = EventAccount::create(['name' => 'A', 'active' => true]);
        self::assertSame(['saving', 'creating', 'created', 'saved'], EventAccount::$log);

        EventAccount::$log = [];
        $account->name = 'B';
        $account->save();
        self::assertSame(['saving', 'updating', 'updated', 'saved'], EventAccount::$log);

        EventAccount::$log = [];
        $account->delete();
        self::assertSame(['deleting', 'deleted'], EventAccount::$log);
    }

    public function testCreatingReturningFalseHaltsInsert(): void
    {
        EventAccount::creating(static fn () => false);

        $account = EventAccount::create(['name' => 'Blocked', 'active' => true]);

        self::assertFalse($account->exists);
        self::assertSame(0, EventAccount::query()->count());
    }

    public function testLocalScope(): void
    {
        EventAccount::create(['name' => 'on', 'active' => true]);
        EventAccount::create(['name' => 'off', 'active' => false]);

        self::assertCount(1, EventAccount::query()->active()->get());
        self::assertCount(1, EventAccount::active()->get());
    }

    public function testGlobalScopeHidesAndCanBeRemoved(): void
    {
        SoftDoc::create(['title' => 'has title', 'status' => 'open']);
        SoftDoc::query()->getQuery()->insert(['title' => null, 'status' => 'open']); // bypass scope to seed

        self::assertSame(1, SoftDoc::query()->count());
        self::assertSame(2, SoftDoc::query()->withoutGlobalScope('only_titled')->count());
    }

    public function testSoftDeleteHidesRestoresAndForceDeletes(): void
    {
        $doc = SoftDoc::create(['title' => 'A', 'status' => 'open']);

        self::assertTrue($doc->delete());
        self::assertTrue($doc->trashed());
        self::assertNull(SoftDoc::find($doc->id));
        self::assertSame(0, SoftDoc::query()->count());

        self::assertInstanceOf(SoftDoc::class, SoftDoc::query()->withTrashed()->find($doc->id));
        self::assertCount(1, SoftDoc::query()->onlyTrashed()->get());

        $trashed = SoftDoc::query()->withTrashed()->find($doc->id);
        self::assertTrue($trashed->restore());
        self::assertInstanceOf(SoftDoc::class, SoftDoc::find($doc->id));

        self::assertTrue($doc->forceDelete());
        self::assertSame(0, SoftDoc::query()->withTrashed()->count());
    }

    public function testBulkDeleteIsSoftForSoftDeletableModels(): void
    {
        SoftDoc::create(['title' => 'A', 'status' => 'open']);
        SoftDoc::create(['title' => 'B', 'status' => 'open']);

        $affected = SoftDoc::where('status', 'open')->delete();

        self::assertSame(2, $affected);
        self::assertSame(0, SoftDoc::query()->count());
        self::assertSame(2, SoftDoc::query()->withTrashed()->count());
    }
}
