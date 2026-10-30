@extends('layouts.admin')
@use('App\Support\Theme')

@section('title', $superblog->name)

@section('content')
    @php
        // Tabs are plain links with ?flik=..., so only the active tab is rendered and no JS is needed.
        $tab = in_array(request()->query('flik'), ['utseende', 'floden'], true) ? request()->query('flik') : 'grund';
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
                <a href="{{ route('admin.superblogs.edit', [$superblog, 'flik' => 'floden']) }}" @if ($tab === 'floden') aria-current="page" @endif class="{{ $tabClasses }}">3 Flöden</a>
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
    @elseif ($tab === 'floden')
        @php
            // The dashboard card shows the same value. Swedish only here, since the app locale is English.
            $lastFetchedAt = $rssSources->max('last_fetched_at');
            $lastFetched = $lastFetchedAt ? ucfirst($lastFetchedAt->locale('sv')->diffForHumans()) : 'Aldrig';
        @endphp

        <section aria-labelledby="feeds-heading" class="mt-6 rounded-xl border border-studio-line bg-studio-panel p-6">
            <h2 id="feeds-heading" class="text-lg font-semibold">RSS-flöden</h2>
            <p class="mt-1 text-[13px] leading-normal text-studio-muted">Senast hämtat: {{ $lastFetched }}.</p>

            @if (session('error'))
                <div role="alert" class="mt-4 rounded-lg border border-studio-bad/40 bg-[#43261c] p-4 text-studio-bad">{{ session('error') }}</div>
            @endif

            <form method="POST" action="{{ route('admin.superblogs.rss-sources.store', $superblog) }}" class="mt-4 flex flex-col gap-2 rounded-[10px] border border-dashed border-[#50525d] p-3">
                @csrf
                {{-- fran brings the user back to this tab; without it the controller goes to the RSS list. --}}
                <input type="hidden" name="fran" value="byggaren">
                {{-- The backend requires is_active, and Figma adds new feeds as active. --}}
                <input type="hidden" name="is_active" value="1">
                <h3 class="text-[13px] font-semibold">Lägg till flöde</h3>
                <input name="name" value="{{ old('name') }}" required maxlength="255" placeholder="Namn, t.ex. Lokalnytt" aria-label="Flödets namn" class="w-full rounded-lg border border-studio-line bg-studio-bg p-3 text-sm focus-visible:outline-2 focus-visible:outline-studio-brand">
                <input name="url" type="url" value="{{ old('url') }}" required maxlength="65535" placeholder="https://exempel.se/rss.xml" aria-label="Flödets adress" class="w-full rounded-lg border border-studio-line bg-studio-bg p-3 text-sm focus-visible:outline-2 focus-visible:outline-studio-brand">
                <button type="submit" class="self-end rounded-lg bg-studio-brand px-4 py-2 text-sm font-medium text-studio-side hover:bg-[#ff9068] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Lägg till</button>
            </form>

            <h3 class="mt-[22px] mb-2 text-[13px] font-semibold tracking-[.04em]">Tillagda flöden <small class="ml-1 text-studio-muted">{{ $rssSources->count() }}</small></h3>

            @if ($rssSources->isEmpty())
                <div class="flex flex-col items-start gap-1.5 rounded-[10px] border border-dashed border-studio-line p-4 text-[13px] leading-[1.45]">
                    <b>Inga flöden ännu</b>
                    <span class="text-studio-muted">Lägg till ditt första RSS-flöde ovan.</span>
                </div>
            @else
                <ul class="flex flex-col gap-2.5">
                    @foreach ($rssSources as $rssSource)
                        <li @class(['flex flex-col gap-2 rounded-[10px] border border-studio-line p-[11px]', 'opacity-60' => ! $rssSource->is_active])>
                            <p class="text-sm font-semibold">{{ $rssSource->name }}</p>
                            <p class="break-all text-[13px] text-studio-muted">{{ $rssSource->url }}</p>
                            {{-- The count is stored RSS items, not published articles. --}}
                            <small class="text-[13px] text-studio-muted">{{ $rssSource->is_active ? 'Aktiv · '.$rssSource->rss_items_count.' '.($rssSource->rss_items_count === 1 ? 'artikel' : 'artiklar') : 'Pausad' }}</small>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
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
