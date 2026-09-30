<?php

namespace App\Models;

use App\Enums\ContactEventAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit entry of contact sharing on a match: consent granted, consent revoked, or
 * the other participant's contact details viewed.
 *
 * @property int $id
 * @property int $match_id
 * @property int $user_id
 * @property ContactEventAction $action
 * @property string|null $ip_address
 */
class ContactEvent extends Model
{
    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => ContactEventAction::class,
        ];
    }

    /**
     * Records `$action` by `$user` on `$match`.
     */
    public static function record(MobilityMatch $match, User $user, ContactEventAction $action, ?string $ipAddress): self
    {
        $event = new self;
        $event->forceFill([
            'match_id' => $match->id,
            'user_id' => $user->id,
            'action' => $action,
            'ip_address' => $ipAddress,
        ])->save();

        return $event;
    }

    /**
     * @return BelongsTo<MobilityMatch, $this>
     */
    public function mobilityMatch(): BelongsTo
    {
        return $this->belongsTo(MobilityMatch::class, 'match_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
