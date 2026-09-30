<?php

namespace Tests\Support;

use Closure;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Laravel\SerializableClosure\UnsignedSerializableClosure;
use RuntimeException;

/**
 * Runs the same work in several real PHP processes released at the same instant, against the
 * shared test database (pilot gate "Les jobs sont idempotents et testes sous concurrence").
 *
 * The data a test prepares must be committed for the workers to see it: this uses
 * {@see DatabaseTruncation} instead of RefreshDatabase, and empties the tables again afterwards so
 * that the transactional tests that follow start from an empty database. Only PostgreSQL is
 * shared between processes; on the default in-memory SQLite the test is skipped.
 */
trait RunsConcurrently
{
    use DatabaseTruncation;

    protected function requireSharedDatabase(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Concurrency tests run on PostgreSQL only (phpunit.pgsql.xml).');
        }

        $this->beforeApplicationDestroyed(fn () => $this->truncateTablesForAllConnections());
    }

    /**
     * @param  Closure(int): mixed  $work  a static closure, given the worker index (0 to $processes - 1)
     * @param  array<string, string>  $environment  variables added to the workers' environment
     * @return list<mixed> each worker's result, in worker order
     */
    protected function runConcurrently(Closure $work, int $processes = 4, array $environment = []): array
    {
        $closureFile = (string) tempnam(sys_get_temp_dir(), 'badal-concurrency-');
        file_put_contents($closureFile, serialize(new UnsignedSerializableClosure($work)));

        $connection = (string) config('database.default');
        $env = [
            ...getenv(),
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => $connection,
            'DB_DATABASE' => (string) config("database.connections.{$connection}.database"),
            'DB_URL' => '',
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER' => 'array',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            ...$environment,
        ];

        // Every worker boots before the start time, then all of them run the work together.
        $startAt = microtime(true) + 3;
        $workers = [];

        for ($index = 0; $index < $processes; $index++) {
            $pipes = [];
            $process = proc_open(
                [PHP_BINARY, base_path('tests/Support/concurrent-worker.php'), $closureFile, sprintf('%.6F', $startAt), (string) $index],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                base_path(),
                $env,
            );

            if (! is_resource($process)) {
                throw new RuntimeException('Could not start a concurrency worker.');
            }

            $workers[] = [$process, $pipes];
        }

        $results = [];

        foreach ($workers as $index => [$process, $pipes]) {
            $output = (string) stream_get_contents($pipes[1]);
            $errors = (string) stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);

            if ($exitCode !== 0) {
                throw new RuntimeException("Concurrency worker {$index} failed ({$exitCode}): {$errors}{$output}");
            }

            $results[] = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        }

        @unlink($closureFile);

        return $results;
    }
}
