<?php

namespace App\Broadcasting;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;

class UserChannel
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
     * @param  int  $userId
     * @return array|bool
     */
    public function join(Authenticatable $user, $userId)
    {
        // Allow user to join their own channel
        return (int) $user->id === (int) $userId;
    }
}

