@extends('layouts.admin')
@use('App\Support\Theme')

@section('title', $superblog->name)

@section('content')
    @php
        // Tabs are plain links with ?flik=..., so only the active tab is rendered and no JS is needed.
        $tab = request()->query('flik') === 'utseende' ? 'utseende' : 'grund';
        $tabClasses = 'inline-block border-b-2 border-transparent px-1 pb-3 text-sm font-medium text-studio-muted hover:text-studio-ink aria-[current=page]:border-studio-brand aria-[current=page]:text-studio-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand';
        $selectedTemplate = old('template_id', $superblog->template_id);
    @endphp

    <a href="{{ route('admin.dashboard') }}" class="text-sm underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand"><span aria-hidden="true">←</span> Alla magasin</a>

    <div class="mt-4 flex flex-wrap items-center gap-3">
        <h1 class="text-3xl font-semibold tracking-tight">{{ $superblog->name }}</h1>
        <x-admin.status-pill :live="$superblog->status === 'published'" />
    </div>

    <nav aria-label="Byggarens steg" class="mt-8 border-b border-studio-line">
        <ul class="flex flex-wrap gap-6">
            <li>
                <a href="{{ route('admin.superblogs.edit', [$superblog, 'flik' => 'grund']) }}" @if ($tab === 'grund') aria-current="page" @endif class="{{ $tabClasses }}">1 Grund</a>
            </li>
            <li>
                <a href="{{ route('admin.superblogs.edit', [$superblog, 'flik' => 'utseende']) }}" @if ($tab === 'utseende') aria-current="page" @endif class="{{ $tabClasses }}">2 Utseende</a>
            </li>
            <li>
                {{-- Leaves the builder for the existing RSS pages until feeds are built into it. --}}
                <a href="{{ route('admin.superblogs.rss-sources.index', $superblog) }}" class="{{ $tabClasses }}">3 Flöden <span aria-hidden="true">↗</span></a>
            </li>
        </ul>
    </nav>

    @if ($errors->any())
        <x-admin.errors class="mt-6" />
    @endif

    @if ($tab === 'grund')
        <form method="POST" action="{{ route('admin.superblogs.update', $superblog) }}" class="mt-6 space-y-6 rounded-xl border border-studio-line bg-studio-panel p-6">
            @csrf
            @method('PUT')
            {{-- Without a slug, SaveSuperblogRequest regenerates it from the name, so renaming would change it. --}}
            <input type="hidden" name="slug" value="{{ old('slug', $superblog->slug) }}">

            <div>
                <label for="name" class="block text-sm font-medium">Namn</label>
                <input id="name" name="name" value="{{ old('name', $superblog->name) }}" required maxlength="255" class="mt-2 w-full rounded-lg border border-studio-line bg-studio-bg p-3 focus-visible:outline-2 focus-visible:outline-studio-brand">
            </div>

            <fieldset>
                <legend class="text-sm font-medium">Mall</legend>
                @include('admin.superblogs._template-options')
            </fieldset>

            <x-admin.domain-input id="domain" :value="old('domain', $superblog->domain)" help="Skriv bara domänen, utan https://.">
                Webbadress (valfri)
            </x-admin.domain-input>

            <div>
                <button type="submit" class="rounded-lg bg-studio-brand px-4 py-2 text-sm font-medium text-studio-side hover:bg-[#ff9068] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Spara</button>
            </div>
        </form>
    @else
        @php
            // Theme::name() returns null for a missing or unknown stored name, so nothing is preselected then.
            $currentTheme = Theme::name($superblog->settings['theme'] ?? null);
            $selectedTheme = old('theme', $currentTheme);
            // Full class names on purpose: Tailwind only generates classes it finds as complete strings.
            $swatches = ['bg-paper', 'bg-ink', 'bg-primary', 'bg-deep', 'bg-pale', 'bg-line'];
        @endphp

        <form method="POST" action="{{ route('admin.superblogs.theme.update', $superblog) }}" class="mt-6 space-y-6 rounded-xl border border-studio-line bg-studio-panel p-6">
            @csrf
            @method('PUT')

            <div>
                <h2 class="text-lg font-semibold">Utseende</h2>
                <p class="mt-1 text-sm text-studio-muted">Välj ett tema för magasinets publika sida. Fler färger och typsnitt kommer senare.</p>
                @if ($currentTheme === null)
                    <p class="mt-2 text-sm text-studio-muted">Inget tema är valt än.</p>
                @endif
            </div>

            <fieldset>
                <legend class="sr-only">Tema</legend>
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach (Theme::NAMES as $name)
                        <label class="flex cursor-pointer flex-col gap-3 rounded-lg border border-studio-line p-4 has-checked:border-studio-brand has-checked:ring-3 has-checked:ring-studio-brand/20">
                            <span class="flex items-center gap-3">
                                <input type="radio" name="theme" value="{{ $name }}" required @checked($selectedTheme === $name)>
                                <span class="text-sm font-medium">{{ ucfirst($name) }}</span>
                            </span>
                            {{-- data-theme scopes the theme's CSS variables to this preview; no values are written into a style attribute. --}}
                            <span data-theme="{{ $name }}" aria-hidden="true" class="block rounded-md bg-paper p-3 text-ink">
                                <span class="flex gap-1.5">
                                    @foreach ($swatches as $swatch)
                                        <span class="size-6 rounded border border-line {{ $swatch }}"></span>
                                    @endforeach
                                </span>
                                <span class="mt-3 block font-display text-lg font-semibold">Aa Rubrik</span>
                                <span class="block font-body text-sm text-muted">Brödtext i temats typsnitt</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div>
                <button type="submit" class="rounded-lg bg-studio-brand px-4 py-2 text-sm font-medium text-studio-side hover:bg-[#ff9068] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Spara</button>
            </div>
        </form>
    @endif
@endsection
