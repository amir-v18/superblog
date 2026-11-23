@props(['article'])

@php($contentModifiedAt = $article->content_modified_at)
@php($hasContentModification = $contentModifiedAt && $contentModifiedAt->greaterThan($article->created_at))

<div {{ $attributes->class('grid gap-y-0.5') }}>
    <div>
        <span>Skapad:</span>
        <time datetime="{{ $article->created_at->toIso8601String() }}">{{ $article->created_at->locale('sv')->isoFormat('D MMM YYYY') }}</time>
    </div>
    @if ($hasContentModification)
        <div>
            <span>Senast ändrad:</span>
            <time datetime="{{ $contentModifiedAt->toIso8601String() }}">{{ $contentModifiedAt->locale('sv')->isoFormat('D MMM YYYY') }}</time>
        </div>
    @endif
</div>
