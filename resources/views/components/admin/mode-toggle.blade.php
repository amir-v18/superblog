{{-- Switches the admin between its two color modes. The server reads the same cookie as layouts.admin, so the
     button always offers the other mode. A plain form, so it also works without JS; app.js switches in place. --}}
@php
    $isLight = request()->cookie(App\Http\Controllers\StudioModeController::COOKIE) === 'light';
@endphp
<form method="POST" action="{{ route('studio-mode.update') }}" data-studio-mode-form {{ $attributes }}>
    @csrf
    <input type="hidden" name="mode" value="{{ $isLight ? 'dark' : 'light' }}" data-studio-mode-next>
    <button type="submit" aria-label="{{ $isLight ? 'Byt till mörkt läge' : 'Byt till ljust läge' }}"
            class="inline-flex h-7 items-center gap-1.5 rounded-lg border border-studio-line bg-studio-panel px-2.5 text-xs font-semibold text-studio-muted hover:text-studio-ink studio-focus-ring">
        {{-- Sun, offered while the page is in its default mode. --}}
        <svg data-studio-mode-icon="light" @if ($isLight) hidden @endif aria-hidden="true" viewBox="0 0 24 24" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <circle cx="12" cy="12" r="4" />
            <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
        </svg>
        {{-- Moon, offered while the page is in its light mode. --}}
        <svg data-studio-mode-icon="dark" @if (! $isLight) hidden @endif aria-hidden="true" viewBox="0 0 24 24" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
        </svg>
        <span data-studio-mode-label>{{ $isLight ? 'Mörkt' : 'Ljust' }}</span>
    </button>
</form>
