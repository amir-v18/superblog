@props(['id', 'value' => null, 'help'])

{{-- The slot is the label text. Extra attributes (e.g. data-domain-target) go on the input. --}}
<div>
    <label for="{{ $id }}" class="block text-sm font-medium">{{ $slot }}</label>
    {{-- The prefix is only visual: the backend expects a bare hostname. --}}
    <div class="mt-2 flex rounded-lg border border-studio-line bg-studio-bg focus-within:outline-2 focus-within:outline-studio-brand">
        <span aria-hidden="true" class="flex items-center rounded-l-lg bg-studio-pill px-3 text-sm text-studio-muted">https://</span>
        <input id="{{ $id }}" name="domain" value="{{ $value }}" maxlength="253" placeholder="dagensbudbarare.se" aria-describedby="{{ $id }}-help"
               {{ $attributes->merge(['class' => 'w-full min-w-0 rounded-r-lg bg-transparent p-3 placeholder:text-[#70727c] focus:outline-none']) }}>
    </div>
    <p id="{{ $id }}-help" class="mt-1 text-sm text-studio-muted">{{ $help }}</p>
</div>
