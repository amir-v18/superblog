@props(['sources' => [], 'brand' => null])

{{-- Source citations and the brand website share one concise sources section. --}}
@if (count($sources) > 0 || $brand)
    <aside aria-labelledby="sources-heading" {{ $attributes->merge(['class' => 'rounded-md border border-line p-5']) }}>
        <h2 id="sources-heading" class="font-display text-lg font-semibold">Källor</h2>
        <ul class="mt-3 space-y-2 text-sm">
            @foreach ($sources as $source)
                <li>
                    <a href="{{ $source['url'] }}" class="text-primary underline underline-offset-4 hover:text-deep focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                        {{ $source['name'] }}
                    </a>
                </li>
            @endforeach
            @if ($brand)
                <li><span class="text-muted">Varumärkets webbplats:</span> <x-product-link :href="$brand['url']">{{ $brand['anchor_text'] }}</x-product-link></li>
            @endif
        </ul>
    </aside>
@endif
