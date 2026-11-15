<?php

namespace Chargefield\Store\Tests;

use Chargefield\Store\StoreServiceProvider;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Orchestra\Testbench\TestCase;

/**
 * Guards the nightly masters:rename schedule entry.
 *
 * The command declares --write as a no-value flag. Scheduling it with the
 * array-argument form (['--write' => true]) makes Laravel's scheduler compile
 * it to "masters:rename --write=1", which Symfony rejects at input-parse time
 * ("The \"--write\" option does not accept a value") — so the nightly job
 * crashed every run before it touched a file. Calling the command via
 * Artisan::call() with the same array does NOT reproduce this (ArrayInput
 * treats true as "flag present"), which is why the bug reached production.
 *
 * This test inspects the compiled schedule itself, so it fails on the old
 * array-argument line and passes once the flag lives in the command string.
 */
class MastersRenameScheduleTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [StoreServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        // The nightly rename is only scheduled when auto_rename is on.
        $app['config']->set('store.masters.auto_rename', true);
    }

    public function test_masters_rename_is_scheduled_as_a_bare_write_flag(): void
    {
        /** @var Schedule $schedule */
        $schedule = $this->app->make(Schedule::class);

        $events = array_values(array_filter(
            $schedule->events(),
            fn (Event $event) => str_contains((string) $event->command, 'masters:rename'),
        ));

        $this->assertCount(1, $events, 'masters:rename should be scheduled exactly once.');

        $command = (string) $events[0]->command;

        // A bare "--write" is what the command accepts; "--write=<value>" is the
        // compiled form that crashes the scheduled run.
        $this->assertStringContainsString('--write', $command);
        $this->assertStringNotContainsString('--write=', $command);
    }
}
