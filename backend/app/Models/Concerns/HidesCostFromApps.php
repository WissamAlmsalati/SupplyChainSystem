<?php

namespace App\Models\Concerns;

use App\Enums\UserRole;

/**
 * What the goods cost the company is for the dashboard only.
 *
 * The fields named in $costFields are hidden whenever the row is serialized,
 * unless the viewer is a signed-in dashboard account. Hidden is the default on
 * purpose: a guest reading the public catalogue, a cafe, a delegate, a queued
 * job or a new endpoint nobody thought about all get the safe answer, and only
 * the one audience that should see the margin has to qualify for it.
 */
trait HidesCostFromApps
{
    public function getHidden()
    {
        $hidden = parent::getHidden();

        return self::viewerSeesCost() ? $hidden : array_values(array_unique([...$hidden, ...$this->costFields]));
    }

    public static function viewerSeesCost(): bool
    {
        $user = auth()->user();

        return $user !== null
            && method_exists($user, 'hasRole')
            && ! $user->hasRole(UserRole::Customer, UserRole::Delegate);
    }
}
