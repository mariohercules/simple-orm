<?php

declare(strict_types=1);

namespace SimpleORM\Model\Concerns;

/**
 * Lifecycle events for models. Listeners are registered per concrete class and
 * receive the model instance. A "before" listener (creating/updating/saving/
 * deleting/restoring) that returns false halts the operation.
 *
 * Register via the static helpers (User::creating(...)), or attach an observer
 * object whose method names match events: User::observe(UserObserver::class).
 */
trait HasEvents
{
    /** @var array<class-string,array<string,array<int,callable>>> */
    protected static array $modelListeners = [];

    /** @var array<int,string> */
    protected static array $observableEvents = [
        'retrieved', 'creating', 'created', 'updating', 'updated',
        'saving', 'saved', 'deleting', 'deleted', 'restoring', 'restored',
    ];

    public static function registerModelEvent(string $event, callable $callback): void
    {
        static::$modelListeners[static::class][$event][] = $callback;
    }

    /**
     * Remove all event listeners registered for this model class (e.g. between tests).
     */
    public static function flushEventListeners(): void
    {
        unset(static::$modelListeners[static::class]);
    }

    public static function creating(callable $cb): void
    {
        static::registerModelEvent('creating', $cb);
    }

    public static function created(callable $cb): void
    {
        static::registerModelEvent('created', $cb);
    }

    public static function updating(callable $cb): void
    {
        static::registerModelEvent('updating', $cb);
    }

    public static function updated(callable $cb): void
    {
        static::registerModelEvent('updated', $cb);
    }

    public static function saving(callable $cb): void
    {
        static::registerModelEvent('saving', $cb);
    }

    public static function saved(callable $cb): void
    {
        static::registerModelEvent('saved', $cb);
    }

    public static function deleting(callable $cb): void
    {
        static::registerModelEvent('deleting', $cb);
    }

    public static function deleted(callable $cb): void
    {
        static::registerModelEvent('deleted', $cb);
    }

    public static function restoring(callable $cb): void
    {
        static::registerModelEvent('restoring', $cb);
    }

    public static function restored(callable $cb): void
    {
        static::registerModelEvent('restored', $cb);
    }

    public static function retrieved(callable $cb): void
    {
        static::registerModelEvent('retrieved', $cb);
    }

    /**
     * Attach an observer whose methods (creating, saved, ...) map to events.
     */
    public static function observe(string|object $observer): void
    {
        $instance = is_string($observer) ? new $observer() : $observer;

        foreach (static::$observableEvents as $event) {
            if (method_exists($instance, $event)) {
                static::registerModelEvent($event, [$instance, $event]);
            }
        }
    }

    /**
     * Fire an event. When $halt is true, a listener returning false stops the
     * chain and this returns false (used to veto save/delete).
     */
    protected function fireModelEvent(string $event, bool $halt = true): bool
    {
        foreach (static::$modelListeners[static::class][$event] ?? [] as $listener) {
            $result = $listener($this);

            if ($halt && $result === false) {
                return false;
            }
        }

        return true;
    }
}
