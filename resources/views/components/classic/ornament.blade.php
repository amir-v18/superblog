@props(['decorative' => false])

@php
    $classes = 'classic-ornament mt-1 mb-3 flex items-center gap-3 text-center font-caps text-xs font-normal tracking-[.22em] text-muted uppercase';
@endphp

{{-- A section heading between two double rules. A real heading by default, so the page keeps a proper heading
     order; the decorative one (the stars above the footer) is skipped by screen readers. --}}
@if ($decorative)
    <div aria-hidden="true" {{ $attributes->class($classes) }}>{{ $slot }}</div>
@else
    <h2 {{ $attributes->class($classes) }}>{{ $slot }}</h2>
@endif
