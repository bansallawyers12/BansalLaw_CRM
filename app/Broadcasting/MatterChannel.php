<?php

namespace App\Broadcasting;

use App\Models\Staff;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

class MatterChannel
{
    /**
     * Create a new channel instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Authenticate the user's access to the channel.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  int  $matterId
     * @return array|bool
     */
    public function join(Authenticatable $user, $matterId)
    {
        // Check if user is associated with this matter
        $isAssociated = DB::table('client_matters')
            ->where('id', $matterId)
            ->where(function($query) use ($user) {
                $query->where('sel_legal_practitioner', $user->id)
                      ->orWhere('sel_person_responsible', $user->id)
                      ->orWhere('sel_person_assisting', $user->id);
            })
            ->exists();

        $isSuperAdmin = $user instanceof Staff && $user->hasEffectiveSuperAdminPrivileges();

        return $isAssociated || $isSuperAdmin;
    }
}

