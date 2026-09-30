<?php

namespace Tests\Feature;

use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class BladeComponentsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('fr');
    }

    public function test_input_links_label_help_and_error_to_the_control(): void
    {
        $view = $this->blade('<x-input name="email" type="email" label="Courriel" help="Aide" error="Adresse invalide" required />');

        $view->assertSee('<label for="email"', false);
        $view->assertSee('id="email"', false);
        $view->assertSee('aria-describedby="email-help email-error"', false);
        $view->assertSee('aria-invalid="true"', false);
        $view->assertSee('id="email-help"', false);
        $view->assertSee('id="email-error" role="alert"', false);
        $view->assertSee('required', false);
    }

    public function test_input_without_error_is_not_marked_invalid(): void
    {
        $view = $this->blade('<x-input name="email" label="Courriel" />');

        $view->assertDontSee('aria-invalid', false);
        $view->assertDontSee('aria-describedby', false);
        $view->assertDontSee('role="alert"', false);
    }

    public function test_input_reads_its_error_from_the_validation_error_bag(): void
    {
        $view = $this->withViewErrors(['email' => 'Champ invalide'])
            ->blade('<x-input name="email" label="Courriel" />');

        $view->assertSee('Champ invalide');
        $view->assertSee('aria-invalid="true"', false);
        $view->assertSee('aria-describedby="email-error"', false);
    }

    public function test_input_does_not_render_password_values(): void
    {
        $view = $this->blade('<x-input name="password" type="password" label="Mot de passe" value="secret" />');

        $view->assertDontSee('secret');
    }

    public function test_textarea_select_and_checkbox_expose_the_same_accessibility_wiring(): void
    {
        $this->blade('<x-textarea name="note" label="Note" help="Aide" />')
            ->assertSee('aria-describedby="note-help"', false);

        $this->blade('<x-select name="city" label="Ville" :options="[\'a\' => \'Alpha\', \'b\' => \'Beta\']" value="b" placeholder />')
            ->assertSee('<option value="b" selected>Beta</option>', false);

        $this->blade('<x-checkbox name="consent" label="J’accepte" error="Obligatoire" required />')
            ->assertSee('aria-describedby="consent-error"', false)
            ->assertSee('aria-invalid="true"', false);
    }

    public function test_alert_uses_the_role_matching_its_urgency(): void
    {
        $this->blade('<x-alert type="danger">Échec</x-alert>')
            ->assertSee('role="alert"', false)
            ->assertSee('Erreur');

        $this->blade('<x-alert type="info">Note</x-alert>')
            ->assertSee('role="status"', false)
            ->assertSee('Information');
    }

    public function test_button_renders_as_link_or_busy_button(): void
    {
        $this->blade('<x-button href="/x">Aller</x-button>')->assertSee('<a href="/x"', false);

        $this->blade('<x-button type="submit" :loading="true">Envoyer</x-button>')
            ->assertSee('type="submit"', false)
            ->assertSee('aria-busy="true"', false)
            ->assertSee('disabled', false);
    }

    public function test_badge_card_and_empty_state_render_their_content(): void
    {
        $this->blade('<x-status-badge tone="success">Vérifié</x-status-badge>')->assertSee('Vérifié');

        $this->blade('<x-card heading="Titre" level="3">Corps</x-card>')
            ->assertSee('<h3', false)
            ->assertSee('Corps');

        $this->blade('<x-empty-state title="Rien ici" description="Aucun élément." />')
            ->assertSee('Rien ici')
            ->assertSee('Aucun élément.');
    }

    public function test_icon_is_decorative_unless_titled(): void
    {
        $this->blade('<x-icon name="check" />')->assertSee('aria-hidden="true"', false);
        $this->blade('<x-icon name="check" title="Validé" />')
            ->assertSee('role="img" aria-label="Validé"', false)
            ->assertDontSee('aria-hidden', false);
    }

    public function test_pagination_links_previous_and_next_pages(): void
    {
        $paginator = new LengthAwarePaginator(range(1, 10), 30, 10, 2, ['path' => '/items']);

        $view = $this->blade('<x-pagination :paginator="$paginator" />', ['paginator' => $paginator]);

        $view->assertSee('<nav aria-label="Pagination"', false);
        $view->assertSee('rel="prev"', false);
        $view->assertSee('rel="next"', false);
        $view->assertSee('Page 2 sur 3');
    }

    public function test_pagination_disables_the_previous_link_on_first_page_and_hides_with_single_page(): void
    {
        $first = new LengthAwarePaginator(range(1, 10), 30, 10, 1, ['path' => '/items']);

        $this->blade('<x-pagination :paginator="$paginator" />', ['paginator' => $first])
            ->assertSee('aria-disabled="true"', false)
            ->assertDontSee('rel="prev"', false);

        $single = new LengthAwarePaginator(range(1, 3), 3, 10, 1, ['path' => '/items']);

        $this->blade('<x-pagination :paginator="$paginator" />', ['paginator' => $single])
            ->assertDontSee('<nav', false);
    }
}
