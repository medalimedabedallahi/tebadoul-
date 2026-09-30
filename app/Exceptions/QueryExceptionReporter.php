<?php

namespace App\Exceptions;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * Reports a failed query without personal data.
 *
 * The message of a QueryException embeds the bound values, and PostgreSQL adds the offending
 * key in its DETAIL line ("Key (email)=(...) already exists"). Neither the message, the
 * bindings nor the exception object are logged: only the SQL template, the SQLSTATE and the
 * first application frame that triggered the query.
 */
class QueryExceptionReporter
{
    public function __invoke(QueryException $e): void
    {
        Log::error('Database query failed.', [
            'sql_state' => $e->errorInfo[0] ?? null,
            'connection' => $e->connectionName,
            'sql' => $e->getSql(),
            'origin' => $this->applicationFrame($e),
        ]);
    }

    private function applicationFrame(QueryException $e): ?string
    {
        foreach ($e->getTrace() as $frame) {
            $file = $frame['file'] ?? null;

            if ($file !== null && ! str_contains($file, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR)) {
                return str_replace(base_path().DIRECTORY_SEPARATOR, '', $file).':'.($frame['line'] ?? 0);
            }
        }

        return null;
    }
}
