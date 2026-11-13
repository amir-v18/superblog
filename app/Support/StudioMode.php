<?php

namespace App\Support;

use App\Http\Controllers\StudioModeController;
use Illuminate\Http\Request;

class StudioMode
{
    /**
     * Whether this request shows the admin in light mode. A signed-in user gets the choice saved on their account,
     * so a choice made by someone else in the same browser never carries over; a guest gets the browser's cookie.
     * Without a choice the default look applies. The admin layout and the mode switch both ask here, so they
     * always agree.
     */
    public static function isLight(?Request $request = null): bool
    {
        $request ??= request();
        $user = $request->user();

        return $user
            ? $user->studio_mode === 'light'
            : $request->cookie(StudioModeController::COOKIE) === 'light';
    }
}
