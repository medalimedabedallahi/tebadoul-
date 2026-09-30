/*
 * Menu principal repliable sur petit écran (amélioration progressive).
 *
 * Sans JavaScript, le menu reste entièrement visible. Avec, la classe `js` (posée dans <head>)
 * masque le panneau sous le point de rupture md, et le bouton [data-nav-toggle] l'ouvre ou le
 * ferme en tenant aria-expanded à jour. Échap le referme et rend le focus au bouton.
 */
const toggle = document.querySelector('[data-nav-toggle]');
const panel = toggle ? document.getElementById(toggle.getAttribute('aria-controls')) : null;

function setOpen(open) {
    toggle.setAttribute('aria-expanded', String(open));
    panel.toggleAttribute('data-open', open);
}

if (toggle && panel) {
    toggle.addEventListener('click', () => setOpen(toggle.getAttribute('aria-expanded') !== 'true'));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
            setOpen(false);
            toggle.focus();
        }
    });
}

/*
 * Session expirée (419) pendant une action Livewire : même proposition d'actualiser que Livewire,
 * mais dans la langue de la page (message fourni par <meta name="session-expired-message">).
 */
function localizeSessionExpiry(livewire) {
    const message = document.querySelector('meta[name="session-expired-message"]')?.content;
    let asked = false;

    if (!message) {
        return;
    }

    livewire.interceptRequest(({ onError }) => {
        onError(({ response, preventDefault }) => {
            if (response.status !== 419) {
                return;
            }

            preventDefault();

            if (!asked) {
                asked = true;

                if (window.confirm(message)) {
                    window.location.reload();
                }
            }
        });
    });
}

if (window.Livewire) {
    localizeSessionExpiry(window.Livewire);
} else {
    document.addEventListener('livewire:init', () => localizeSessionExpiry(window.Livewire));
}
