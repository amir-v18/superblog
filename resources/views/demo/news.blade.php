@extends('layouts.public')

@section('title', 'Demo av nyhetsteman')
@section('meta_description', 'En lokal demosida som visar färger och typsnitt för nyhetsmallens teman.')

@section('content')
    @php
        // Full class names on purpose: Tailwind only generates classes it finds as complete strings.
        $swatches = [
            'paper' => 'bg-paper',
            'white' => 'bg-white',
            'ink' => 'bg-ink',
            'muted' => 'bg-muted',
            'primary' => 'bg-primary',
            'deep' => 'bg-deep',
            'line' => 'bg-line',
            'pale' => 'bg-pale',
        ];
    @endphp

    <div class="rounded-lg bg-paper p-6 font-body text-ink sm:p-10">
        <nav aria-label="Välj tema" class="flex flex-wrap gap-3 text-sm font-medium">
            @foreach (\App\Support\Theme::NAMES as $name)
                <a href="{{ route('demo.news', ['theme' => $name]) }}"
                   @if ($name === $theme) aria-current="page" @endif
                   class="rounded-full border border-line px-4 py-1.5 aria-[current=page]:bg-primary aria-[current=page]:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                    {{ $name }}
                </a>
            @endforeach
        </nav>

        <h1 class="mt-8 font-display text-4xl font-semibold tracking-tight">Så ser temat {{ $theme }} ut</h1>
        <p class="mt-4 text-lg text-muted">En ingress med låtsastext. Den visar brödtypsnittet i en dämpad färg.</p>

        <h2 class="mt-10 font-display text-2xl font-semibold">Färger</h2>
        <ul class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
            @foreach ($swatches as $name => $class)
                <li>
                    <div class="h-16 rounded-md border border-line {{ $class }}"></div>
                    <p class="mt-2 text-sm font-medium">{{ $name }}</p>
                    <p class="text-xs text-muted">{{ $class }}</p>
                </li>
            @endforeach
        </ul>

        <h2 class="mt-10 font-display text-2xl font-semibold">Typografi</h2>
        <h3 class="mt-4 font-display text-xl font-semibold">En mindre rubrik i display-typsnittet</h3>
        <p class="mt-3 leading-7">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer posuere erat a ante venenatis dapibus. Donec ullamcorper nulla non metus auctor fringilla.</p>

        <div class="mt-8 rounded-md bg-pale p-5">
            <p class="font-medium text-deep">En ruta i pale med text i deep.</p>
            <a href="#" class="mt-4 inline-block rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-deep focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">Primärknapp</a>
        </div>
    </div>
@endsection
