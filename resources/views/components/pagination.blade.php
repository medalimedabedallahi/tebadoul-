{{--
    Pagination simple (précédent / suivant + position), compatible paginate() et simplePaginate().
    @props paginator: instance de Paginator ou LengthAwarePaginator ; rien n'est rendu s'il n'y a qu'une page
--}}
@props(['paginator'])

@if ($paginator->hasPages())
    <nav aria-label="{{ __('common.pagination.label') }}" {{ $attributes->merge(['class' => 'flex flex-wrap items-center justify-between gap-3']) }}>
        @if ($paginator->onFirstPage())
            <span aria-disabled="true" class="inline-flex min-h-target items-center gap-2 px-4 text-base text-ink-muted">
                <x-icon name="chevron-start" mirror />
                {{ __('common.pagination.previous') }}
            </span>
        @else
            <x-button :href="$paginator->previousPageUrl()" variant="secondary" rel="prev">
                <x-icon name="chevron-start" mirror />
                {{ __('common.pagination.previous') }}
            </x-button>
        @endif

        <p class="text-sm text-ink-muted">
            @if (method_exists($paginator, 'lastPage'))
                {{ __('common.pagination.page_of', ['current' => $paginator->currentPage(), 'last' => $paginator->lastPage()]) }}
            @else
                {{ __('common.pagination.page', ['current' => $paginator->currentPage()]) }}
            @endif
        </p>

        @if ($paginator->hasMorePages())
            <x-button :href="$paginator->nextPageUrl()" variant="secondary" rel="next">
                {{ __('common.pagination.next') }}
                <x-icon name="chevron-end" mirror />
            </x-button>
        @else
            <span aria-disabled="true" class="inline-flex min-h-target items-center gap-2 px-4 text-base text-ink-muted">
                {{ __('common.pagination.next') }}
                <x-icon name="chevron-end" mirror />
            </span>
        @endif
    </nav>
@endif
