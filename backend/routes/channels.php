<?php

use App\Enums\UserRole;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Live driver positions. The same rule as the delegates page: a dashboard
// account holding DELEGATES_VIEW, or the super admin.
Broadcast::channel('delegates.locations', function ($user) {
    if (! $user->is_active || $user->hasRole(UserRole::Customer, UserRole::Delegate)) {
        return false;
    }
    if ($user->hasRole(UserRole::SuperAdmin)) {
        return true;
    }

    return (bool) $user->loadMissing('userType.permissions')->userType?->permissions->contains('code', 'DELEGATES_VIEW');
});
