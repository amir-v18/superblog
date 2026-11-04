@props(['theme' => null, 'name', 'variant' => 'card'])

@php
    // Only allowlisted theme names reach data-theme; anything else gets the grey fallback.
    $themeName = \App\Support\Theme::name($theme);

    // Complete class strings per variant, since Tailwind only generates classes it finds written out in full.
    // card: the magazine card on the dashboard. template: Figma's template card in the create modal
    // (96 px in total: the 8 px strip plus an 88 px area, so the strip sits on top of Figma's 18 px top padding).
    $classes = match (true) {
        $variant === 'template' && $themeName !== null => [
            'strip' => 'h-2 bg-primary',
            'area' => 'flex h-22 flex-col justify-between overflow-hidden border-b border-primary/33 bg-paper px-3.5 pt-2.5 pb-4.5 text-ink',
            'title' => 'mt-2 truncate font-display text-lg leading-none font-semibold tracking-[-.03em]',
            'bars' => 'grid grid-cols-[2fr_1fr_1fr] gap-1.5',
            'bar' => ['h-[34px] rounded-[3px] bg-accent opacity-65', 'h-[34px] rounded-[3px] bg-primary opacity-65', 'h-[34px] rounded-[3px] bg-accent opacity-65'],
        ],
        $variant === 'template' => [
            'strip' => 'h-2 bg-studio-line',
            'area' => 'flex h-22 flex-col justify-between overflow-hidden border-b border-studio-line bg-studio-bg px-3.5 pt-2.5 pb-4.5 text-studio-muted',
            'title' => 'mt-2 truncate text-lg leading-none font-semibold tracking-[-.03em]',
            'bars' => 'grid grid-cols-[2fr_1fr_1fr] gap-1.5',
            'bar' => ['h-[34px] rounded-[3px] bg-studio-pill', 'h-[34px] rounded-[3px] bg-studio-line', 'h-[34px] rounded-[3px] bg-studio-line'],
        ],
        $themeName !== null => [
            'strip' => 'h-2 bg-primary',
            'area' => 'flex h-32 flex-col justify-between bg-paper p-4 text-ink',
            'title' => 'truncate font-display text-2xl font-semibold',
            'bars' => 'grid h-8 grid-cols-[2fr_1fr_1fr] gap-2',
            'bar' => ['rounded-md bg-primary/90', 'rounded-md bg-deep/70', 'rounded-md bg-pale'],
        ],
        default => [
            'strip' => 'h-2 bg-studio-line',
            'area' => 'flex h-32 flex-col justify-between bg-studio-bg p-4 text-studio-muted',
            'title' => 'truncate text-2xl font-semibold',
            'bars' => 'grid h-8 grid-cols-[2fr_1fr_1fr] gap-2',
            'bar' => ['rounded-md bg-studio-pill', 'rounded-md bg-studio-line', 'rounded-md bg-studio-line'],
        ],
    };
@endphp

{{-- Decorative: the name is repeated next to it, which is what screen readers get. data-theme scopes the theme's
     CSS variables to this element, so no color values are written into a style attribute. Same shape with or
     without a theme, so cards in a row stay the same height. --}}
<div @if ($themeName) data-theme="{{ $themeName }}" @endif aria-hidden="true">
    <div class="{{ $classes['strip'] }}"></div>
    <div class="{{ $classes['area'] }}">
        <p class="{{ $classes['title'] }}">{{ $name }}</p>
        <div class="{{ $classes['bars'] }}">
            @foreach ($classes['bar'] as $bar)
                <span class="{{ $bar }}"></span>
            @endforeach
        </div>
    </div>
</div>
