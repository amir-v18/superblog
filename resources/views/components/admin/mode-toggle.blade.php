{{-- Switches the admin between its two color modes. The server asks App\Support\StudioMode, like layouts.admin, so
     the button of the current mode is marked as pressed; without a choice that is the default mode. A plain form
     with one button per mode, so it also works without JS; app.js switches in place. Below 640 px the icons carry
     the look, and the words stay readable by assistive software. --}}
@php
    $isLight = App\Support\StudioMode::isLight();
    $buttonClasses = 'inline-flex h-5 items-center justify-center gap-1 rounded-md px-2 text-xs font-semibold text-studio-muted hover:text-studio-ink aria-pressed:bg-studio-brand aria-pressed:text-studio-on-brand studio-focus-ring';
@endphp
<form method="POST" action="{{ route('studio-mode.update') }}" data-studio-mode-form {{ $attributes }}>
    @csrf
    <div role="group" aria-label="Färgläge" class="grid grid-cols-2 gap-0.75 rounded-[9px] border border-studio-contrast/12 bg-studio-contrast/6 p-0.75 studio-light:border-studio-line">
        <button type="submit" name="mode" value="dark" aria-label="Mörkt läge" aria-pressed="{{ $isLight ? 'false' : 'true' }}" data-studio-mode-option class="{{ $buttonClasses }}">
            <svg aria-hidden="true" viewBox="0 0 24 24" class="size-3 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
            </svg>
            <span class="max-sm:sr-only">Mörkt</span>
        </button>
        <button type="submit" name="mode" value="light" aria-label="Ljust läge" aria-pressed="{{ $isLight ? 'true' : 'false' }}" data-studio-mode-option class="{{ $buttonClasses }}">
            <svg aria-hidden="true" viewBox="0 0 24 24" class="size-3 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <circle cx="12" cy="12" r="4" />
                <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
            </svg>
            <span class="max-sm:sr-only">Ljust</span>
        </button>
    </div>
</form>
