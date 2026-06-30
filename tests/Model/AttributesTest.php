<?php

declare(strict_types=1);

namespace SimpleORM\Tests\Model;

use PHPUnit\Framework\TestCase;
use SimpleORM\Model\Model;

final class AttributesTest extends TestCase
{
    private function model(): Model
    {
        return new class extends Model {
            protected array $fillable = ['name', 'active', 'meta'];
            protected array $casts = ['active' => 'boolean', 'meta' => 'array'];
            protected array $hidden = ['secret'];
            public bool $timestamps = false;
        };
    }

    public function testMassAssignmentHonoursFillable(): void
    {
        $model = $this->model();
        $model->fill(['name' => 'Alice', 'id' => 99, 'role' => 'admin']);

        self::assertSame('Alice', $model->name);
        self::assertNull($model->id, 'id is not in $fillable and must be ignored');
        self::assertNull($model->role, 'role is not in $fillable and must be ignored');
    }

    public function testBooleanCastRoundTrip(): void
    {
        $model = $this->model();
        $model->active = 1;

        self::assertTrue($model->active);
        self::assertSame(1, $model->getAttributes()['active'], 'stored as int for the DB');
    }

    public function testArrayCastRoundTrip(): void
    {
        $model = $this->model();
        $model->meta = ['tags' => ['x', 'y']];

        self::assertSame(['tags' => ['x', 'y']], $model->meta);
        self::assertIsString($model->getAttributes()['meta'], 'stored as json string for the DB');
    }

    public function testDirtyTracking(): void
    {
        $model = $this->model();
        $model->setRawAttributes(['name' => 'Alice'], sync: true);

        self::assertFalse($model->isDirty());

        $model->name = 'Bob';
        self::assertTrue($model->isDirty());
        self::assertTrue($model->isDirty('name'));
        self::assertFalse($model->isDirty('active'));
        self::assertSame(['name' => 'Bob'], $model->getDirty());
    }

    public function testHiddenExcludedFromArrayAndJson(): void
    {
        $model = $this->model();
        $model->forceFill(['name' => 'Alice', 'secret' => 'shh']);

        $array = $model->toArray();
        self::assertArrayHasKey('name', $array);
        self::assertArrayNotHasKey('secret', $array);
        self::assertStringNotContainsString('shh', $model->toJson());
    }
}
