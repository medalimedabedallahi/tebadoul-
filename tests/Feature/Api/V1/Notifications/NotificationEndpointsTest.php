<?php

namespace Tests\Feature\Api\V1\Notifications;

use App\Enums\NotificationType;
use App\Enums\TokenAbility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_gets_401(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
        $this->patchJson('/api/v1/me/preferences', ['email_notifications' => false])->assertUnauthorized();
    }

    public function test_the_list_exposes_an_allow_list_and_the_unread_count(): void
    {
        $user = User::factory()->create();
        $notification = $this->notificationFor($user);

        $this->withToken($this->tokenFor($user))->getJson('/api/v1/notifications?unread=1')
            ->assertOk()
            ->assertJsonPath('meta.unread_count', 1)
            ->assertJsonPath('meta.total', 1)
            ->assertExactJsonStructure([
                'data' => [['id', 'type', 'match_public_id', 'read_at', 'created_at']],
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'from', 'last_page', 'links', 'path', 'per_page', 'to', 'total', 'unread_count'],
            ])
            ->assertJsonPath('data.0.id', $notification->id)
            ->assertJsonPath('data.0.type', 'invitation_received')
            ->assertJsonPath('data.0.match_public_id', '01jzmatch0000000000000000');
    }

    public function test_reading_marks_one_or_all_and_another_account_notification_gets_404(): void
    {
        $user = User::factory()->create();
        $mine = $this->notificationFor($user);
        $this->notificationFor($user);
        $theirs = $this->notificationFor(User::factory()->create());
        $token = $this->tokenFor($user);

        $this->withToken($token)->postJson("/api/v1/notifications/{$mine->id}/read")
            ->assertOk()
            ->assertJsonPath('data.id', $mine->id);
        $this->assertNotNull($mine->fresh()->read_at);

        $this->withToken($token)->postJson("/api/v1/notifications/{$theirs->id}/read")->assertNotFound();
        $this->withToken($token)->postJson('/api/v1/notifications/not-a-uuid/read')->assertNotFound();
        $this->assertNull($theirs->fresh()->read_at);

        $this->withToken($token)->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertExactJson(['data' => ['marked' => 1]]);
    }

    public function test_preferences_are_updated_and_an_unsupported_language_is_rejected(): void
    {
        $user = User::factory()->create(['locale' => 'fr']);
        $token = $this->tokenFor($user);

        $this->withToken($token)->patchJson('/api/v1/me/preferences', ['email_notifications' => false, 'locale' => 'ar'])
            ->assertOk()
            ->assertJsonPath('data.email_notifications', false)
            ->assertJsonPath('data.locale', 'ar');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email_notifications' => false, 'locale' => 'ar']);

        $this->withToken($token)->patchJson('/api/v1/me/preferences', ['locale' => 'en'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors(['locale' => 'La valeur sélectionnée pour langue est invalide.']);
    }

    private function notificationFor(User $user): DatabaseNotification
    {
        /** @var DatabaseNotification */
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => NotificationType::InvitationReceived->value,
            'data' => ['match_public_id' => '01jzmatch0000000000000000'],
        ]);
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('test', [TokenAbility::AccessApi->value])->plainTextToken;
    }
}
