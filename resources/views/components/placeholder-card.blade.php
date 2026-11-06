@props(['stripes', 'stripeLabel', 'article' => null, 'superblog' => null])

{{-- Real articles fill this same card; empty slots keep the existing striped placeholder. --}}
<article class="flex flex-col gap-1.75">
    <div class="aspect-16/10 overflow-hidden rounded-[3px] border-[1.5px] border-dashed border-ink/30 {{ $stripes }}"><span class="{{ $stripeLabel }}">Bild</span></div>
    @if ($article && $superblog)
        <h3 class="my-1.75 line-clamp-2 min-h-11 font-display text-sm leading-[1.35] font-semibold">
            <a href="{{ route('public.superblogs.articles.show', [$superblog, $article]) }}" target="_blank" rel="noopener" class="hover:underline">{{ $article->title }}</a>
        </h3>
        <span class="text-[11px] text-ink/60">
            @if ($article->published_at)
                {{ $article->published_at->locale('sv')->isoFormat('D MMM YYYY') }}
            @else
                Utkast
            @endif
        </span>
    @else
        <div class="my-1.75 h-2.75 w-[92%] rounded-[3px] bg-ink/14"></div>
        <div class="my-1.75 h-2.75 w-[70%] rounded-[3px] bg-ink/14"></div>
        <span class="text-[11px] opacity-45">Tid · Författare</span>
    @endif
</article>
