<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudioModeController extends Controller
{
    /**
     * Name of the cookie that holds the admin's chosen color mode. Read by layouts.admin.
     */
    public const COOKIE = 'studio_mode';

    public const MODES = ['light', 'dark'];

    /**
     * Saves the chosen mode for this browser. A cookie rather than a user column, so the choice also works on the
     * login page and needs no migration; Laravel encrypts it like every other cookie.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mode' => ['required', 'string', Rule::in(self::MODES)],
        ]);

        // url()->previous() trusts the Referer header as is, so only a page on this host is used as the target.
        $previous = url()->previous();
        $target = parse_url($previous, PHP_URL_HOST) === $request->getHost()
            ? $previous
            : route('admin.dashboard');

        return redirect()->to($target)
            ->withCookie(cookie()->forever(self::COOKIE, $validated['mode']));
    }
}
