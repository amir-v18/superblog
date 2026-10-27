@props(['title' => 'Rätta följande:'])

{{-- The caller decides when to show it (e.g. the modal only for its own form) and sets the spacing via class. --}}
<div role="alert" {{ $attributes->merge(['class' => 'rounded-lg border border-studio-bad/40 bg-[#43261c] p-4 text-studio-bad']) }}>
    <p class="font-medium">{{ $title }}</p>
    <ul class="mt-2 list-inside list-disc">
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
