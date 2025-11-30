<?php

namespace App\Utilities;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class UserHelper
{
    /**
     * Get a user instance that works in any context
     */
    public static function getUser()
    {
        // Try standard Laravel auth
        if (Auth::check()) {
            return Auth::user();
        }
        // Fallback to system user for command context
        return User::where('email','ayirosprings@gmail.com')->first();
    }

    public static function getUserContacts($user_id){
        return Contact::where('user_id', $user_id)->get();
    }
}
