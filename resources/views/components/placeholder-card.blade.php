@props(['stripes', 'stripeLabel'])

{{-- Stands in for an article that does not exist yet. The stripe classes come from the caller, so the hero and the cards share one definition. --}}
<article class="flex flex-col gap-[7px]">
    <div class="aspect-[16/10] rounded border-[1.5px] border-dashed border-ink/30 {{ $stripes }}"><span class="{{ $stripeLabel }}">Bild</span></div>
    <div class="my-[7px] h-[11px] rounded-[3px] bg-ink/14 w-[92%]"></div>
    <div class="my-[7px] h-[11px] rounded-[3px] bg-ink/14 w-[70%]"></div>
    <span class="text-[11px] opacity-45">Tid · Författare</span>
</article>
