<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Select the locale of the request among `app.supported_locales`.
     *
     * Resolution order: `locale` session key, `locale` cookie, Accept-Language header, then
     * `app.locale`. A candidate that is not supported is skipped, never trusted.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $default = app()->getLocale();
        $locale = $this->resolve($request);

        app()->setLocale($locale);

        try {
            $response = $next($request);
        } finally {
            // setLocale() also rewrites `app.locale`: restore it so that the choice of one request
            // never becomes the default of the next one (Octane, queue workers, tests).
            app()->setLocale($default);
        }

        $response->headers->set('Content-Language', $locale);

        return $response;
    }

    private function resolve(Request $request): string
    {
        $candidates = [
            $request->hasSession() ? $request->session()->get('locale') : null,
            $request->cookies->get('locale'),
            ...$this->acceptedLanguages($request),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $this->isSupported($candidate)) {
                return $candidate;
            }
        }

        return (string) config('app.locale');
    }

    /**
     * Primary language subtags of Accept-Language, best quality first ("fr-FR" gives "fr").
     *
     * @return list<string>
     */
    private function acceptedLanguages(Request $request): array
    {
        $languages = [];

        foreach ($request->getLanguages() as $language) {
            $languages[] = strtolower(preg_split('/[-_]/', $language)[0]);
        }

        return $languages;
    }

    private function isSupported(string $locale): bool
    {
        return in_array($locale, (array) config('app.supported_locales'), true);
    }
}
