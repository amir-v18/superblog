<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class StudioModeController extends Controller
{
    /**
     * Name of the cookie that holds a guest's chosen color mode. Read by App\Support\StudioMode for guests.
     */
    public const COOKIE = 'studio_mode';

    public const MODES = ['light', 'dark'];

    /**
     * Saves the chosen mode: on the account when someone is signed in, otherwise in a cookie for this browser, so the
     * login page can switch too. Laravel encrypts the cookie like every other cookie.
     */
    public function __invoke(Request $request): RedirectResponse|Response
    {
        $validated = $request->validate([
            'mode' => ['required', 'string', Rule::in(self::MODES)],
        ]);

        // Signed in, the choice belongs to the account, so the next person in this browser starts from their own.
        // A guest's choice goes into the cookie instead.
        $user = $request->user();
        if ($user) {
            $user->studio_mode = $validated['mode'];
            $user->save();
        }
        $cookie = $user ? null : cookie()->forever(self::COOKIE, $validated['mode']);

        // app.js switches the page itself and just needs the choice saved; a redirect to the whole page would be wasted.
        if ($request->expectsJson()) {
            return $cookie ? response()->noContent()->withCookie($cookie) : response()->noContent();
        }

        // url()->previous() trusts the Referer header as is, so only a page on this host is used as the target.
        $previous = url()->previous();
        $target = parse_url($previous, PHP_URL_HOST) === $request->getHost()
            ? $previous
            : route('admin.dashboard');

        $response = redirect()->to($target);

        return $cookie ? $response->withCookie($cookie) : $response;
    }
}
