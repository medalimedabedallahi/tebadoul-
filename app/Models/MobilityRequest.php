<?php

namespace App\Models;

use App\Enums\MobilityRequestStatus;
use App\Enums\RequestCloseReason;
use App\Exceptions\MobilityRequests\InvalidRequestTransitionException;
use Database\Factories\MobilityRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A mobility request of an account: accepted destinations, availability and expiry. The origin
 * and the professional context are the owner's {@see ProfessionalProfile}.
 *
 * Status changes go through {@see self::transitionTo()}, which enforces
 * {@see MobilityRequestStatus::allowedTransitions()}.
 *
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property MobilityRequestStatus $status
 * @property Carbon $available_from
 * @property Carbon $expires_at
 * @property Carbon|null $published_at
 * @property Carbon|null $closed_at
 * @property RequestCloseReason|null $close_reason
 */
#[Fillable(['available_from', 'expires_at'])]
#[RouteKey('public_id')]
class MobilityRequest extends Model
{
    /** @use HasFactory<MobilityRequestFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * Maximum number of destinations of one request.
     */
    public const MAX_DESTINATIONS = 5;

    /**
     * How far in the future the expiry date may be set, in days.
     */
    public const MAX_VALIDITY_DAYS = 365;

    /**
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
            'status' => MobilityRequestStatus::class,
            'available_from' => 'date',
            'expires_at' => 'date',
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
            'close_reason' => RequestCloseReason::class,
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
     * @return HasMany<RequestDestination, $this>
     */
    public function destinations(): HasMany
    {
        return $this->hasMany(RequestDestination::class)->orderBy('priority');
    }

    /**
     * Moves to `$status`, or throws when the lifecycle does not allow it. The caller saves.
     *
     * @throws InvalidRequestTransitionException
     */
    public function transitionTo(MobilityRequestStatus $status, string $operation): void
    {
        if (! $this->status->canTransitionTo($status)) {
            throw new InvalidRequestTransitionException($this->status, $operation);
        }

        $this->status = $status;

        if ($status === MobilityRequestStatus::Published) {
            $this->published_at ??= now();
        }

        if ($status === MobilityRequestStatus::Closed) {
            $this->closed_at = now();
        }
    }

    /**
     * Operations the owner may perform now, so that clients never rebuild the lifecycle rules
     * themselves (ADR 0001).
     *
     * @return list<string>
     */
    public function allowedActions(): array
    {
        $status = $this->status;

        return array_values(array_filter([
            $status->isEditable() ? 'update' : null,
            in_array($status, [MobilityRequestStatus::Draft, MobilityRequestStatus::Paused], true) ? 'publish' : null,
            $status === MobilityRequestStatus::Published ? 'pause' : null,
            $status === MobilityRequestStatus::Expired ? 'renew' : null,
            $status->canTransitionTo(MobilityRequestStatus::Closed) ? 'close' : null,
            $status->isDeletable() ? 'delete' : null,
        ]));
    }
}
