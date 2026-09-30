<?php

namespace Tests\Feature\Livewire\Auth;

use App\Livewire\Auth\LogoutButton;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LogoutButtonTest extends TestCase
{
    use RefreshDatabase;

    public function test_signs_the_current_user_out_and_redirects_home(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(LogoutButton::class)
            ->call('logout')
            ->assertRedirect(route('home'));

        $this->assertGuest();
    }
}
