<?php

namespace App\Support\Trust;

use App\Exceptions\Trust\InteractionBlockedException;
use App\Models\User;
use App\Models\UserBlock;

final class BlockGuard
{
    /** @throws InteractionBlockedException */
    public function ensureInteractionAllowed(User $first, User $second): void
    {
        if (UserBlock::existsBetween($first->getKey(), $second->getKey())) {
            throw new InteractionBlockedException;
        }
    }
}
