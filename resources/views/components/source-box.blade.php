@props(['sources' => []])

{{-- Each source is ['name' => ..., 'url' => ...]. Real links so readers and crawlers can verify the original. --}}
@if (count($sources) > 0)
    <aside aria-labelledby="sources-heading" {{ $attributes->merge(['class' => 'rounded-md border border-line p-5']) }}>
        <h2 id="sources-heading" class="font-display text-lg font-semibold">Källor</h2>
        <ul class="mt-3 space-y-2">
            @foreach ($sources as $source)
                <li>
                    <a href="{{ $source['url'] }}" class="text-primary underline underline-offset-4 hover:text-deep focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                        {{ $source['name'] }}
                    </a>
                </li>
            @endforeach
        </ul>
    </aside>
@endif
