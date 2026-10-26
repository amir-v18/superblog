@props(['live' => false])

{{-- Figma's .pill: 11px, semibold, 3px/9px padding. Green "● Live" when published, grey "Utkast" otherwise. --}}
@if ($live)
    <span {{ $attributes->merge(['class' => 'rounded-full bg-studio-live px-[9px] py-[3px] text-[11px] font-semibold text-studio-ok']) }}><span aria-hidden="true">●</span> Live</span>
@else
    <span {{ $attributes->merge(['class' => 'rounded-full bg-studio-pill px-[9px] py-[3px] text-[11px] font-semibold text-studio-muted']) }}>Utkast</span>
@endif
