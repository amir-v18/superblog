@props(['href'])

@php
    // The only place rel is set for brand and product links.
    // Open question to the client: "sponsored" or "nofollow". Change it here only.
    $rel = 'sponsored';
@endphp

<a href="{{ $href }}" rel="{{ $rel }}" {{ $attributes->merge(['class' => 'font-medium text-primary underline underline-offset-4 hover:text-deep focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary']) }}>{{ $slot }}</a>
