<?php

namespace App\Support;

use App\Http\Controllers\StudioModeController;
use Illuminate\Http\Request;

class StudioMode
{
    /**
     * Whether this request shows the admin in light mode: the browser's cookie set by StudioModeController.
     * Without a choice the default look applies. The admin layout and the mode switch both ask here, so they
     * always agree.
     */
    public static function isLight(?Request $request = null): bool
    {
        $request ??= request();

        return $request->cookie(StudioModeController::COOKIE) === 'light';
    }
}
