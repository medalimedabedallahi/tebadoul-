<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Pages render without the Vite manifest: CI does not build the frontend before the backend tests.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        $this->assertDatabaseIsDisposable();
    }

    /**
     * Refuse to run against a database that is not dedicated to tests.
     *
     * Environment variables injected by Docker or the shell can take precedence over phpunit.xml,
     * and RefreshDatabase would then wipe the development database.
     */
    private function assertDatabaseIsDisposable(): void
    {
        $connection = config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if ($database !== ':memory:' && ! str_ends_with($database, '_testing')) {
            throw new RuntimeException(
                "Refusing to run tests on database [{$database}]: its name must be ':memory:' or end with '_testing'."
            );
        }
    }
}
