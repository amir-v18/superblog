@extends('layouts.admin')
@use('App\Support\Theme')

@section('title', $superblog->name)

@section('content')
    @php
        // Tabs are plain links with ?flik=..., so only the active tab is rendered and no JS is needed.
        $tab = request()->query('flik') === 'utseende' ? 'utseende' : 'grund';
        $tabClasses = 'inline-block border-b-2 border-transparent px-1 pb-3 text-sm font-medium text-slate-600 hover:text-slate-900 aria-[current=page]:border-slate-900 aria-[current=page]:text-slate-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900';
        $selectedTemplate = old('template_id', $superblog->template_id);
    @endphp

    <a href="{{ route('admin.dashboard') }}" class="text-sm underline underline-offset-4"><span aria-hidden="true">←</span> Alla magasin</a>

    <div class="mt-4 flex flex-wrap items-center gap-3">
        <h1 class="text-3xl font-semibold tracking-tight">{{ $superblog->name }}</h1>
        @if ($superblog->status === 'published')
            <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-800"><span aria-hidden="true">●</span> Live</span>
        @else
            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700">Utkast</span>
        @endif
    </div>

    <nav aria-label="Byggarens steg" class="mt-8 border-b border-slate-200">
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
        <div role="alert" class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4 text-red-900">
            <p class="font-medium">Rätta följande:</p>
            <ul class="mt-2 list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($tab === 'grund')
        <form method="POST" action="{{ route('admin.superblogs.update', $superblog) }}" class="mt-6 space-y-6 rounded-xl border border-slate-200 bg-white p-6">
            @csrf
            @method('PUT')
            {{-- Without a slug, SaveSuperblogRequest regenerates it from the name, so renaming would change it. --}}
            <input type="hidden" name="slug" value="{{ old('slug', $superblog->slug) }}">

            <div>
                <label for="name" class="block text-sm font-medium">Namn</label>
                <input id="name" name="name" value="{{ old('name', $superblog->name) }}" required maxlength="255" class="mt-2 w-full rounded-lg border border-slate-300 p-3">
            </div>

            <fieldset>
                <legend class="text-sm font-medium">Mall</legend>
                @include('admin.superblogs._template-options')
            </fieldset>

            <div>
                <label for="domain" class="block text-sm font-medium">Webbadress (valfri)</label>
                {{-- The prefix is only visual: the backend expects a bare hostname. --}}
                <div class="mt-2 flex rounded-lg border border-slate-300 focus-within:outline-2 focus-within:outline-slate-900">
                    <span aria-hidden="true" class="flex items-center rounded-l-lg bg-slate-100 px-3 text-sm text-slate-500">https://</span>
                    <input id="domain" name="domain" value="{{ old('domain', $superblog->domain) }}" maxlength="253" placeholder="dagensbudbarare.se" aria-describedby="domain-help"
                           class="w-full min-w-0 rounded-r-lg p-3 focus:outline-none">
                </div>
                <p id="domain-help" class="mt-1 text-sm text-slate-600">Skriv bara domänen, utan https://.</p>
            </div>

            <div>
                <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Spara</button>
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

        <form method="POST" action="{{ route('admin.superblogs.theme.update', $superblog) }}" class="mt-6 space-y-6 rounded-xl border border-slate-200 bg-white p-6">
            @csrf
            @method('PUT')

            <div>
                <h2 class="text-lg font-semibold">Utseende</h2>
                <p class="mt-1 text-sm text-slate-600">Välj ett tema för magasinets publika sida. Fler färger och typsnitt kommer senare.</p>
                @if ($currentTheme === null)
                    <p class="mt-2 text-sm text-slate-600">Inget tema är valt än.</p>
                @endif
            </div>

            <fieldset>
                <legend class="sr-only">Tema</legend>
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach (Theme::NAMES as $name)
                        <label class="flex cursor-pointer flex-col gap-3 rounded-lg border border-slate-300 p-4 has-checked:border-slate-900 has-checked:ring-1 has-checked:ring-slate-900">
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
                <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Spara</button>
            </div>
        </form>
    @endif
@endsection
