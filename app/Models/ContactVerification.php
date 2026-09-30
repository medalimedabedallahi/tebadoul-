<?php

namespace App\Models;

use App\Enums\ContactPurpose;
use App\Enums\ContactType;
use Database\Factories\ContactVerificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A one-time code sent to a contact. Only a keyed hash of the code is stored.
 *
 * @property int $id
 * @property int|null $user_id
 * @property ContactPurpose $purpose
 * @property ContactType $channel
 * @property string $contact
 * @property string $code_hash
 * @property int $attempts
 * @property int $max_attempts
 * @property Carbon $expires_at
 * @property Carbon|null $consumed_at
 * @property Carbon|null $last_sent_at
 */
#[Fillable([
    'user_id', 'purpose', 'channel', 'contact', 'code_hash', 'attempts', 'max_attempts',
    'expires_at', 'consumed_at', 'last_sent_at',
])]
#[Hidden(['code_hash', 'contact'])]
class ContactVerification extends Model
{
    /** @use HasFactory<ContactVerificationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => ContactPurpose::class,
            'channel' => ContactType::class,
            'attempts' => 'integer',
            'max_attempts' => 'integer',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'last_sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Codes that have neither been used nor superseded.
     *
     * @param  Builder<ContactVerification>  $query
     */
    public function scopeUnconsumed(Builder $query): void
    {
        $query->whereNull('consumed_at');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isLocked(): bool
    {
        return $this->attempts >= $this->max_attempts;
    }
}
