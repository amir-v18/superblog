<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Throwable;

class GoogleAuthenticationController extends Controller
{
    public function redirect(): RedirectResponse
    {
        if (! $this->googleConfigured()) {
            return to_route('login')->with('error', 'Google sign-in is not configured. Contact your administrator.');
        }

        try {
            return Socialite::driver('google')->with(['prompt' => 'select_account'])->redirect();
        } catch (Throwable) {
            return to_route('login')->with('error', 'Google sign-in is temporarily unavailable. Please try again.');
        }
    }

    public function callback(Request $request): RedirectResponse|View
    {
        if (! $this->googleConfigured()) {
            return to_route('login')->with('error', 'Google sign-in is not configured. Contact your administrator.');
        }

        if ($request->filled('error')) {
            return to_route('login')->with('error', 'Google sign-in was cancelled or could not be completed. Please try again.');
        }

        try {
            /** @var GoogleUser $googleUser */
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable) {
            // This also covers Socialite's OAuth state mismatch; do not bypass state validation.
            return to_route('login')->with('error', 'Google sign-in could not be verified. Please try again.');
        }

        $subject = $googleUser->getId();
        $email = $googleUser->getEmail();
        $raw = $googleUser->getRaw();

        if (! is_string($subject) || $subject === '' || strlen($subject) > 255) {
            return to_route('login')->with('error', 'Google did not provide a valid account identity.');
        }

        $user = User::where('google_subject', $subject)->first();
        if ($user) {
            return $this->signIn($request, $user);
        }

        if (($raw['email_verified'] ?? false) !== true || ! is_string($email)
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 255) {
            return to_route('login')->with('error', 'Use a Google account with a verified email address.');
        }

        $identity = [
            'subject' => $subject,
            'email' => $email,
            'name' => $googleUser->getName(),
        ];

        if (User::where('email', $email)->exists()) {
            return view('auth.google-mapping-required', $identity);
        }

        // Let the operator map existing accounts before a new pending account claims this subject.
        $request->session()->put('google.pending_identity', $identity);

        return view('auth.google-account-setup', $identity);
    }

    public function createAccount(Request $request): RedirectResponse|View
    {
        $identity = $request->session()->get('google.pending_identity');
        if (! is_array($identity) || ! is_string($identity['subject'] ?? null)
            || ! is_string($identity['email'] ?? null)
            || (! is_string($identity['name'] ?? null) && ($identity['name'] ?? null) !== null)) {
            return to_route('login')->with('error', 'Your Google sign-in session expired. Please try again.');
        }

        $linked = User::where('google_subject', $identity['subject'])->first();
        if ($linked) {
            $request->session()->forget('google.pending_identity');

            return $this->signIn($request, $linked);
        }

        if (User::where('email', $identity['email'])->exists()) {
            return view('auth.google-mapping-required', $identity);
        }

        try {
            $user = DB::transaction(function () use ($identity): User {
                // Recheck both unique identities inside the transaction to handle concurrent callbacks safely.
                $linked = User::where('google_subject', $identity['subject'])->first();
                if ($linked) {
                    return $linked;
                }

                if (User::where('email', $identity['email'])->exists()) {
                    throw new GoogleEmailConflict;
                }

                $user = new User;
                $user->name = filled($identity['name']) ? $identity['name'] : $identity['email'];
                $user->email = $identity['email'];
                $user->email_verified_at = now();
                $user->password = Hash::make(Str::random(64));
                $user->approval_status = 'pending';
                $user->is_super_admin = false;
                $user->has_all_superblogs_access = false;
                $user->google_subject = $identity['subject'];
                $user->save();

                return $user;
            });
        } catch (GoogleEmailConflict) {
            return view('auth.google-mapping-required', $identity);
        } catch (UniqueConstraintViolationException) {
            $user = User::where('google_subject', $identity['subject'])->first();
            if (! $user) {
                return view('auth.google-mapping-required', $identity);
            }
        }

        $request->session()->forget('google.pending_identity');

        return $this->signIn($request, $user);
    }

    private function signIn(Request $request, User $user): RedirectResponse
    {
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return to_route('admin.dashboard');
    }

    private function googleConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }
}

class GoogleEmailConflict extends \RuntimeException {}
