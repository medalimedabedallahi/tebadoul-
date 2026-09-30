<?php

/**
 * One worker process of {@see RunsConcurrently}: boots the application, waits for
 * the shared start time so that every worker hits the database at once, runs the serialized
 * closure with its worker index, and prints the JSON-encoded result.
 *
 * Usage: php concurrent-worker.php <closure-file> <start-at-microtime> <worker-index>
 */

use Illuminate\Contracts\Console\Kernel;
use Tests\Support\RunsConcurrently;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$connection = config('database.default');
$database = (string) config("database.connections.{$connection}.database");

// Same protection as Tests\TestCase: never touch a database that is not dedicated to tests.
if (! str_ends_with($database, '_testing')) {
    fwrite(STDERR, "Refusing to run on database [{$database}].\n");
    exit(2);
}

$work = unserialize((string) file_get_contents($argv[1]))->getClosure();
$startAt = (float) $argv[2];

if (microtime(true) < $startAt) {
    time_sleep_until($startAt);
}

echo json_encode($work((int) $argv[3]), JSON_THROW_ON_ERROR);
