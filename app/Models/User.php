<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\ContactType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Support\ContactNormalizer;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $phone_verified_at
 * @property string $password
 * @property UserStatus $status
 * @property UserRole $role
 * @property string $locale
 * @property bool $email_notifications
 */
#[Fillable(['name', 'email', 'phone', 'locale', 'password'])]
#[Hidden(['password', 'remember_token', 'email', 'phone'])]
#[RouteKey('public_id')]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUlids, Notifiable;

    /**
     * In-memory defaults matching the column defaults, so a new model reads like a stored one.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'email_notifications' => true,
    ];

    /**
     * Get the columns that receive a generated ULID.
     *
     * The auto-incrementing primary key stays internal; only `public_id` is exposed.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'role' => UserRole::class,
            'email_notifications' => 'boolean',
        ];
    }

    /**
     * One-time codes issued for the contacts of the account.
     *
     * @return HasMany<ContactVerification, $this>
     */
    public function contactVerifications(): HasMany
    {
        return $this->hasMany(ContactVerification::class);
    }

    /**
     * Accounts whose `$channel` is `$contact` AND is verified: the only contacts that can be used
     * to sign in or to reset a password.
     *
     * @param  Builder<User>  $query
     */
    public function scopeWithVerifiedContact(Builder $query, ContactType $channel, string $contact): void
    {
        $query->where($channel->value, $contact)->whereNotNull($channel->value.'_verified_at');
    }

    /**
     * Accounts still pending verification, without any verified contact, created before `$before`.
     *
     * Accounts named in the audit trail (for example suspended then reinstated while still pending)
     * are excluded: the trail must be retained, and its foreign keys forbid deleting them.
     *
     * @param  Builder<User>  $query
     */
    public function scopeExpiredPendingVerification(Builder $query, CarbonInterface $before): void
    {
        $query->where('status', UserStatus::PendingVerification)
            ->whereNull('email_verified_at')
            ->whereNull('phone_verified_at')
            ->where('created_at', '<', $before)
            ->whereDoesntHave('auditLogs');
    }

    /**
     * @return HasOne<ProfessionalProfile, $this>
     */
    public function professionalProfile(): HasOne
    {
        return $this->hasOne(ProfessionalProfile::class);
    }

    /**
     * @return HasMany<MobilityRequest, $this>
     */
    public function mobilityRequests(): HasMany
    {
        return $this->hasMany(MobilityRequest::class);
    }

    /**
     * Audit entries of administrative decisions taken about this account.
     *
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'target_user_id');
    }

    /** @return HasMany<UserBlock, $this> */
    public function blocksInitiated(): HasMany
    {
        return $this->hasMany(UserBlock::class, 'blocker_id');
    }

    /** @return HasMany<UserBlock, $this> */
    public function blocksReceived(): HasMany
    {
        return $this->hasMany(UserBlock::class, 'blocked_id');
    }

    public function hasVerified(ContactType $channel): bool
    {
        return $this->{$channel->value.'_verified_at'} !== null;
    }

    /**
     * Detach `$channel` from the account (contact and its verification date), and invalidate the
     * unused codes issued for it. The caller saves the model.
     */
    public function detachContact(ContactType $channel): void
    {
        $contact = $this->{$channel->value};

        if ($contact === null) {
            return;
        }

        $this->contactVerifications()->unconsumed()->where('contact', $contact)->update(['consumed_at' => now()]);

        $this->{$channel->value} = null;
        $this->{$channel->value.'_verified_at'} = null;
    }

    /**
     * Store the email trimmed and lower-cased; a blank value is stored as null.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function email(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => ContactNormalizer::email($value));
    }

    /**
     * Store the phone as a simple E.164 number ("+" and digits); a blank value is stored as null.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function phone(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => ContactNormalizer::phone(
            $value,
            (string) config('app.default_phone_country_code', '222'),
        ));
    }
}
