<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The terms of use and the privacy policy are public, in French and Arabic, dated with the
 * version recorded at registration, and marked as drafts until a lawyer validates them.
 */
class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_documents_are_public_dated_and_marked_as_drafts(): void
    {
        foreach (['legal.terms' => 'Conditions d’utilisation', 'legal.privacy' => 'Politique de confidentialité'] as $route => $title) {
            $this->get(route($route))
                ->assertOk()
                ->assertSee($title)
                ->assertSee('Version du 6 octobre 2026')
                ->assertSee(__('legal.draft_notice'));
        }
    }

    public function test_the_privacy_policy_states_the_configured_retention_of_unverified_accounts(): void
    {
        config(['auth.pending_accounts.ttl_days' => 9]);

        $this->get(route('legal.privacy'))->assertSee('supprimé après 9 jours');
    }

    public function test_the_draft_notice_disappears_once_the_texts_are_validated(): void
    {
        config(['legal.draft' => false]);

        $this->get(route('legal.terms'))->assertOk()->assertDontSee(__('legal.draft_notice'));
    }

    public function test_the_documents_are_available_in_arabic_right_to_left(): void
    {
        $this->withSession(['locale' => 'ar'])->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('سياسة الخصوصية');
    }

    public function test_every_page_links_to_both_documents(): void
    {
        $this->get(route('home'))
            ->assertSee('href="'.route('legal.terms').'"', false)
            ->assertSee('href="'.route('legal.privacy').'"', false);
    }
}
