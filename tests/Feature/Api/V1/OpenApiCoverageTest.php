<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Tests\TestCase;

/**
 * Pilot gate "OpenAPI couvre tous les endpoints implementes": every `/api/v1` route is documented
 * in `docs/openapi.yaml`, and every documented operation exists. Path parameters are compared by
 * position only (`{match}` in Laravel, `{matchId}` in the contract).
 */
class OpenApiCoverageTest extends TestCase
{
    public function test_every_api_route_is_documented_and_every_documented_operation_exists(): void
    {
        $implemented = $this->implementedOperations();
        $documented = $this->documentedOperations();

        $this->assertContains('GET /matches/{}', $documented, 'The contract could not be read.');
        $this->assertSame([], array_values(array_diff($implemented, $documented)), 'Routes missing from docs/openapi.yaml.');
        $this->assertSame([], array_values(array_diff($documented, $implemented)), 'Documented operations without a route.');
    }

    /**
     * @return list<string> "METHOD /path" with `{}` for each parameter
     */
    private function implementedOperations(): array
    {
        $operations = [];

        /** @var Route $route */
        foreach (Router::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/')) {
                continue;
            }

            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $operations[] = $method.' '.$this->normalize(substr($route->uri(), strlen('api/v1')));
            }
        }

        sort($operations);

        return $operations;
    }

    /**
     * Reads the `paths` section by its indentation: a path at two spaces, its operations at four.
     *
     * @return list<string>
     */
    private function documentedOperations(): array
    {
        $lines = file(base_path('docs/openapi.yaml'), FILE_IGNORE_NEW_LINES) ?: [];
        $operations = [];
        $inPaths = false;
        $path = null;

        foreach ($lines as $line) {
            if (preg_match('/^(\S[^:]*):/', $line, $section) === 1) {
                $inPaths = $section[1] === 'paths';
                $path = null;

                continue;
            }

            if (! $inPaths) {
                continue;
            }

            if (preg_match('#^  (/\S*):\s*$#', $line, $match) === 1) {
                $path = $this->normalize($match[1]);
            } elseif ($path !== null && preg_match('/^    (get|post|put|patch|delete):\s*$/', $line, $match) === 1) {
                $operations[] = strtoupper($match[1]).' '.$path;
            }
        }

        sort($operations);

        return $operations;
    }

    private function normalize(string $path): string
    {
        return (string) preg_replace('/\{[^}]+\}/', '{}', '/'.ltrim($path, '/'));
    }
}
