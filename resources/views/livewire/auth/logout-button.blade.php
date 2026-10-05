{{--
    Bouton de déconnexion (requête Livewire, donc toujours en POST).
--}}
<form method="post" wire:submit="logout">
    <button
        type="submit"
        class="inline-flex min-h-target items-center whitespace-nowrap rounded-control px-3 text-base font-medium text-ink-soft hover:bg-brand-soft hover:text-ink"
    >
        {{ __('nav.logout') }}
    </button>
</form>
