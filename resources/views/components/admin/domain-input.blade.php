@props(['id', 'value' => null, 'help', 'error' => null])

{{-- The slot is the label text. Extra attributes (e.g. data-domain-target) go on the input. --}}
<div>
    <label for="{{ $id }}" class="block text-sm font-medium">{{ $slot }}</label>
    {{-- The prefix is only visual: the backend expects a bare hostname. An optional error message is drawn under the box and announced with the field; the directives sit at the start of their lines so the markup is unchanged without one. --}}
    <div class="mt-2 flex rounded-lg border border-studio-line bg-studio-bg focus-within:outline-2 focus-within:outline-studio-brand studio-light:border-studio-field-line">
        <span aria-hidden="true" class="flex items-center rounded-l-lg bg-studio-pill px-3 text-sm text-studio-muted">https://</span>
        <input id="{{ $id }}" name="domain" value="{{ $value }}" maxlength="253" placeholder="dagensbudbarare.se" aria-describedby="{{ $error ? $id.'-error ' : '' }}{{ $id }}-help"
               {{ $attributes->merge(['class' => 'w-full min-w-0 rounded-r-lg bg-transparent p-3 placeholder:text-studio-placeholder focus:outline-none', 'aria-invalid' => $error ? 'true' : null]) }}>
    </div>
@if ($error)
    <p id="{{ $id }}-error" class="mt-2 text-[13px] font-semibold text-studio-bad">{{ $error }}</p>
@endif
    <p id="{{ $id }}-help" class="mt-1 text-sm text-studio-muted">{{ $help }}</p>
</div>
