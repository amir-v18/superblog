@props(['article'])

{{-- An article's publishing date. Drafts only reach the builder's preview, where they have no date yet,
     so they say so instead of showing a made-up time. --}}
@if ($article->published_at)
    <time datetime="{{ $article->published_at->toIso8601String() }}" {{ $attributes }}>{{ $article->published_at->locale('sv')->isoFormat('D MMMM YYYY') }}</time>
@else
    <span {{ $attributes }}>Utkast</span>
@endif
