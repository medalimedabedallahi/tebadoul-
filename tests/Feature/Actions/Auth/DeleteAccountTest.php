<?php

namespace Tests\Feature\Actions\Auth;

use App\Actions\Auth\AuthenticateCredentials;
use App\Actions\Auth\DeleteAccount;
use App\Actions\Auth\RegisterUser;
use App\Actions\Matching\AcceptMatch;
use App\Actions\Matching\InviteToMatch;
use App\Actions\Messaging\SendMatchMessage;
use App\Enums\AuditAction;
use App\Enums\MatchStatus;
use App\Enums\MobilityRequestStatus;
use App\Enums\UserStatus;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Models\MatchMessage;
use App\Models\MobilityRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesMatchedPair;
use Tests\Support\InteractsWithContactCodes;
use Tests\TestCase;

class DeleteAccountTest extends TestCase
{
    use CreatesMatchedPair;
    use InteractsWithContactCodes;
    use RefreshDatabase;

    public function test_a_wrong_password_changes_nothing(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);

        try {
            app(DeleteAccount::class)->handle($user, 'wrong-password-2026');
            $this->fail('A wrong password should be refused.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('password', $exception->errors());
        }

        $this->assertSame(UserStatus::Active, $user->fresh()->status);
        $this->assertNotNull($user->fresh()->email);
    }

    public function test_the_account_is_anonymized_its_personal_data_erased_and_the_deletion_audited(): void
    {
        [$rosso, $hodh, $match] = $this->createMatchedPair();
        $rosso->forceFill(['password' => self::PASSWORD, 'phone' => '+22241111111', 'phone_verified_at' => now(), 'google_id' => 'google-deleted-subject'])->save();
        $rosso->createToken('mobile');
        $this->freezeSecond();

        app(DeleteAccount::class)->handle($rosso, self::PASSWORD, '192.0.2.10');

        $rosso->refresh();
        $this->assertSame(UserStatus::Deleted, $rosso->status);
        $this->assertSame(DeleteAccount::ANONYMIZED_NAME, $rosso->name);
        $this->assertNull($rosso->email);
        $this->assertNull($rosso->phone);
        $this->assertNull($rosso->google_id);
        $this->assertNull($rosso->email_verified_at);
        $this->assertNull($rosso->phone_verified_at);
        $this->assertTrue(now()->equalTo($rosso->anonymized_at));
        $this->assertSame(0, $rosso->tokens()->count());
        $this->assertNull($rosso->professionalProfile()->first());
        $this->assertSame(0, $rosso->notifications()->count());
        $this->assertSame(0, $rosso->contactVerifications()->count());
        $this->assertSame(0, MobilityRequest::query()->where('user_id', $rosso->id)->count());
        $this->assertSame(1, MobilityRequest::withTrashed()->where('user_id', $rosso->id)->count());
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $rosso->id,
            'target_user_id' => $rosso->id,
            'action' => AuditAction::AccountDeleted->value,
            'ip_address' => '192.0.2.10',
        ]);
        $this->assertSame(MatchStatus::Invalidated, $match->fresh()->status);
        $this->assertNotNull($hodh->fresh()->professionalProfile()->first());
    }

    public function test_an_agreement_ends_the_other_request_is_released_and_messages_stay_with_their_recipient(): void
    {
        [$rosso, $hodh, $match] = $this->createMatchedPair();
        app(InviteToMatch::class)->handle($rosso, $match->public_id);
        app(AcceptMatch::class)->handle($hodh, $match->public_id);
        app(SendMatchMessage::class)->handle($rosso, $match->public_id, 'Bonjour');
        $rosso->forceFill(['password' => self::PASSWORD])->save();

        app(DeleteAccount::class)->handle($rosso, self::PASSWORD);

        $this->assertSame(MatchStatus::Invalidated, $match->fresh()->status);
        $this->assertSame(MobilityRequestStatus::Published, MobilityRequest::query()->where('user_id', $hodh->id)->sole()->status);
        $this->assertSame(1, MatchMessage::query()->where('sender_id', $rosso->id)->count());
    }

    public function test_the_old_contacts_no_longer_sign_in_and_can_be_registered_again(): void
    {
        $user = User::factory()->create(['email' => 'aminetou@example.com', 'password' => self::PASSWORD]);
        $this->captureContactCodes();

        app(DeleteAccount::class)->handle($user, self::PASSWORD);

        try {
            app(AuthenticateCredentials::class)->handle('aminetou@example.com', self::PASSWORD);
            $this->fail('A deleted account must not sign in.');
        } catch (InvalidCredentialsException) {
            // Expected: the address no longer belongs to any account.
        }

        app(RegisterUser::class)->handle('Aminetou', 'aminetou@example.com', null, self::PASSWORD);

        $this->assertSame(UserStatus::PendingVerification, User::query()->where('email', 'aminetou@example.com')->sole()->status);
    }

    public function test_a_deleted_account_cannot_be_deleted_again_nor_suspended(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);
        app(DeleteAccount::class)->handle($user, self::PASSWORD);

        $this->assertFalse(User::factory()->administrator()->create()->can('suspend', $user->fresh()));

        $this->expectException(AuthorizationException::class);
        app(DeleteAccount::class)->handle($user->fresh(), self::PASSWORD);
    }
}
