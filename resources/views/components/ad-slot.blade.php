{{--
    Espace publicitaire : affiche une publicité active de l'emplacement, pour la langue courante
    ou pour toutes les langues (App\Actions\Advertising\PickAdvertisement), ou rien.
    @props placement: App\Enums\AdPlacement
    @props compact: cadre de pied de page (8:1) au lieu du cadre de page (4:1)
    @props centered: centre le bloc (pages au contenu centré) ; sinon il s'aligne sur le début du contenu
    Les attributs (espacements, conteneur) vont sur <aside> ; la largeur du bloc est bornée à max-w-3xl.
    Le cadre a un format fixe (anneau plutôt que bordure, pour ne pas réduire la zone de l'image) :
    une image d'un autre format y est centrée sans être rognée (même cadre dans l'aperçu admin).
    Toujours signalée comme publicité ; le lien sortant porte rel="sponsored". Aucun traceur, aucun
    cookie : la page de confidentialité l'affirme. Une panne de la base ne doit pas casser la page.
--}}
@props(['placement', 'compact' => false, 'centered' => false])

@php
    try {
        $advertisement = app(\App\Actions\Advertising\PickAdvertisement::class)->handle($placement, app()->getLocale());
    } catch (\Throwable $exception) {
        report($exception);
        $advertisement = null;
    }
    $frameClass = 'block w-full overflow-hidden rounded-control bg-surface ring-1 ring-line '.($compact ? 'aspect-[8/1]' : 'aspect-[4/1]');
@endphp

@if ($advertisement)
    <aside
        aria-label="{{ __('common.advertisement.label') }}"
        {{ $attributes->merge(['class' => 'w-full']) }}
        data-advertisement="{{ $advertisement->public_id }}"
    >
        <div class="flex w-full max-w-3xl flex-col gap-1.5 {{ $centered ? 'mx-auto' : '' }}">
            <p class="text-xs font-medium text-ink-muted">{{ __('common.advertisement.label') }}</p>
            @if ($advertisement->link_url)
                <a
                    href="{{ $advertisement->link_url }}"
                    target="_blank"
                    rel="sponsored noopener noreferrer"
                    class="{{ $frameClass }} hover:ring-line-strong focus-visible:outline-2 focus-visible:outline-offset-2"
                >
                    <img src="{{ $advertisement->imageUrl() }}" alt="{{ $advertisement->title }}" class="size-full object-contain" loading="lazy" decoding="async">
                    <span class="sr-only">{{ __('common.advertisement.new_tab') }}</span>
                </a>
            @else
                <div class="{{ $frameClass }}">
                    <img src="{{ $advertisement->imageUrl() }}" alt="{{ $advertisement->title }}" class="size-full object-contain" loading="lazy" decoding="async">
                </div>
            @endif
        </div>
    </aside>
@endif
