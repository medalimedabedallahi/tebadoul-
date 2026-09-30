<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SwitchLocaleController extends Controller
{
    /**
     * Durée de vie du cookie de langue, en minutes (un an).
     */
    private const COOKIE_MINUTES = 60 * 24 * 365;

    /**
     * Mémorise la langue choisie (session et cookie) puis revient à la page précédente.
     *
     * Une langue absente de `app.supported_locales` est refusée (422 en JSON,
     * retour avec erreur de validation sinon) sans modifier la langue courante.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        /** @var array{locale: string} $validated */
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in($this->supportedLocales())],
        ]);

        $locale = $validated['locale'];

        $request->session()->put('locale', $locale);

        return redirect()
            ->back(fallback: route('home'))
            ->withCookie(cookie('locale', $locale, self::COOKIE_MINUTES));
    }

    /**
     * @return list<string>
     */
    private function supportedLocales(): array
    {
        return array_values(config('app.supported_locales', ['fr', 'ar']));
    }
}
