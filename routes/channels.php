<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// User-specific channels (standard format)
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Private user channels (Pusher format: private-user.{userId})
Broadcast::channel('private-user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Matter-specific channels (standard format)
Broadcast::channel('matter.{matterId}', function ($user, $matterId) {
    // Check if user is associated with this matter
    $isAssociated = DB::table('client_matters')
        ->where('id', $matterId)
        ->where(function($query) use ($user) {
            $query->where('sel_migration_agent', $user->id)
                  ->orWhere('sel_person_responsible', $user->id)
                  ->orWhere('sel_person_assisting', $user->id);
        })
        ->exists();

    // Allow superadmins (role=1) to join any matter channel
    $isSuperAdmin = $user->role == 1;

    return $isAssociated || $isSuperAdmin;
});

// Private matter channels (Pusher format: private-matter.{matterId})
Broadcast::channel('private-matter.{matterId}', function ($user, $matterId) {
    // Check if user is associated with this matter
    $isAssociated = DB::table('client_matters')
        ->where('id', $matterId)
        ->where(function($query) use ($user) {
            $query->where('client_id', $user->id)
                  ->orWhere('sel_migration_agent', $user->id)
                  ->orWhere('sel_person_responsible', $user->id)
                  ->orWhere('sel_person_assisting', $user->id);
        })
        ->exists();

    // Allow superadmins (role=1) to join any matter channel
    $isSuperAdmin = $user->role == 1;

    return $isAssociated || $isSuperAdmin;
});

// Public broadcast channel (for system-wide broadcasts)
Broadcast::channel('broadcasts', function () {
    // Allow all authenticated users to subscribe
    return true;
});