@extends('layouts.admin')
@use('App\Support\Theme')

@section('title', $superblog->name)

{{-- Full width and, from lg, exactly the window height, so the panel and the preview scroll separately as in Figma. --}}
@section('main-class', 'flex w-full flex-col lg:h-screen')

@section('content')
    @php
        // Tabs are plain links with ?flik=..., so only the active tab is rendered and no JS is needed.
        // A new tab (e.g. 4 Innehåll) only needs a line here and a branch below.
        $tabs = ['grund' => '1 Grund', 'utseende' => '2 Utseende', 'floden' => '3 Flöden'];
        $tab = in_array(request()->query('flik'), array_keys($tabs), true) ? request()->query('flik') : 'grund';
        $tabClasses = 'block whitespace-nowrap border-b-2 border-transparent px-2 py-[13px] text-center text-xs font-semibold text-studio-muted hover:text-studio-ink aria-[current=page]:border-studio-brand aria-[current=page]:text-studio-brand focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-studio-brand';
        $selectedTemplate = old('template_id', $superblog->template_id);
    @endphp

    <header class="flex flex-wrap items-center gap-4 border-b border-studio-line bg-studio-side px-5 py-3">
        <a href="{{ route('admin.dashboard') }}" class="rounded-lg px-3.5 py-2 text-[13px] font-semibold text-studio-muted hover:text-studio-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand"><span aria-hidden="true">←</span> Alla magasin</a>
        <div class="flex min-w-0 flex-1 items-center gap-2.5">
            <h1 class="truncate text-lg font-semibold">{{ $superblog->name }}</h1>
            <x-admin.status-pill :live="$superblog->status === 'published'" />
        </div>
        {{-- hidden until app.js runs, since the buttons do nothing without JS. Only from lg, like the preview itself. --}}
        <div role="group" aria-label="Förhandsvisning" hidden data-preview-devices class="hidden rounded-lg bg-studio-panel p-[3px] lg:inline-flex">
            <button type="button" data-preview-device="desktop" aria-pressed="true" class="rounded-md px-3.5 py-1.5 text-xs font-semibold text-studio-muted hover:text-studio-ink aria-pressed:bg-studio-brand aria-pressed:text-studio-side focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Dator</button>
            <button type="button" data-preview-device="mobile" aria-pressed="false" class="rounded-md px-3.5 py-1.5 text-xs font-semibold text-studio-muted hover:text-studio-ink aria-pressed:bg-studio-brand aria-pressed:text-studio-side focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Mobil</button>
        </div>
    </header>

    <div class="lg:grid lg:min-h-0 lg:flex-1 lg:grid-cols-[390px_minmax(0,1fr)]">
        <section aria-label="Inställningar" class="flex flex-col border-studio-line bg-studio-side lg:min-h-0 lg:border-r">
            <nav aria-label="Byggarens steg" class="border-b border-studio-line">
                <ul class="flex overflow-x-auto [scrollbar-width:none]">
                    @foreach ($tabs as $key => $label)
                        <li class="flex-1">
                            <a href="{{ route('admin.superblogs.edit', [$superblog, 'flik' => $key]) }}" @if ($tab === $key) aria-current="page" @endif class="{{ $tabClasses }}">{{ $label }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <div class="flex flex-col gap-3.5 p-5 lg:flex-1 lg:overflow-y-auto">
                @if ($errors->any())
                    <x-admin.errors />
                @endif

                @if ($tab === 'grund')
                    <form method="POST" action="{{ route('admin.superblogs.update', $superblog) }}" class="space-y-6">
                        @csrf
                        @method('PUT')
                        {{-- Without a slug, SaveSuperblogRequest regenerates it from the name, so renaming would change it. --}}
                        <input type="hidden" name="slug" value="{{ old('slug', $superblog->slug) }}">

                        <h2 class="text-lg font-semibold">Grunduppgifter</h2>

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

                    <section aria-labelledby="feeds-heading">
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
                            {{-- A failed card save also flashes name and url, so andrar keeps that text out of this form. --}}
                            <input name="name" value="{{ old('andrar') ? '' : old('name') }}" required maxlength="255" placeholder="Namn, t.ex. Lokalnytt" aria-label="Flödets namn" class="w-full rounded-lg border border-studio-line bg-studio-bg p-3 text-sm focus-visible:outline-2 focus-visible:outline-studio-brand">
                            <input name="url" type="url" value="{{ old('andrar') ? '' : old('url') }}" required maxlength="65535" placeholder="https://exempel.se/rss.xml" aria-label="Flödets adress" class="w-full rounded-lg border border-studio-line bg-studio-bg p-3 text-sm focus-visible:outline-2 focus-visible:outline-studio-brand">
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
                                    @php
                                        $isEditing = (string) old('andrar') === (string) $rssSource->id;
                                    @endphp
                                    <li @class(['flex flex-col gap-2 rounded-[10px] border border-studio-line p-[11px]', 'opacity-60' => ! $rssSource->is_active])>
                                        <div class="flex items-center gap-2.5">
                                            <input form="feed-{{ $rssSource->id }}" name="name" value="{{ $isEditing ? old('name') : $rssSource->name }}" required maxlength="255" aria-label="Flödets namn" class="w-full min-w-0 rounded-lg border border-studio-line bg-studio-bg p-3 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-studio-brand">
                                            {{-- A separate form sending the saved values: Enter in a text field saves instead of toggling, and unsaved text is never saved by the toggle. --}}
                                            <form method="POST" action="{{ route('admin.superblogs.rss-sources.update', [$superblog, $rssSource]) }}" class="flex shrink-0">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="fran" value="byggaren">
                                                <input type="hidden" name="name" value="{{ $rssSource->name }}">
                                                <input type="hidden" name="url" value="{{ $rssSource->url }}">
                                                <input type="hidden" name="is_active" value="{{ $rssSource->is_active ? 0 : 1 }}">
                                                <button type="submit" role="switch" aria-checked="{{ $rssSource->is_active ? 'true' : 'false' }}" aria-label="Aktivt flöde: {{ $rssSource->name }}" @class(['relative h-5 w-[34px] rounded-full transition-colors after:absolute after:top-0.5 after:left-0.5 after:size-4 after:rounded-full after:bg-white after:transition-transform focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand', 'bg-studio-brand after:translate-x-3.5' => $rssSource->is_active, 'bg-[#484a55]' => ! $rssSource->is_active])></button>
                                            </form>
                                        </div>
                                        @if ($rssSource->is_active)
                                            <form id="fetch-{{ $rssSource->id }}" method="POST" action="{{ route('admin.superblogs.rss-sources.fetch', [$superblog, $rssSource]) }}" class="hidden">
                                                @csrf
                                                <input type="hidden" name="fran" value="byggaren">
                                            </form>
                                        @endif
                                        <form id="feed-{{ $rssSource->id }}" method="POST" action="{{ route('admin.superblogs.rss-sources.update', [$superblog, $rssSource]) }}" class="flex flex-col gap-2">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="fran" value="byggaren">
                                            <input type="hidden" name="andrar" value="{{ $rssSource->id }}">
                                            <input type="hidden" name="is_active" value="{{ $rssSource->is_active ? 1 : 0 }}">
                                            <input name="url" type="url" value="{{ $isEditing ? old('url') : $rssSource->url }}" required maxlength="65535" aria-label="Flödets adress" class="w-full rounded-lg border border-studio-line bg-studio-bg p-3 text-sm focus-visible:outline-2 focus-visible:outline-studio-brand">
                                            <div class="flex items-center justify-end gap-3">
                                                @if ($rssSource->is_active)
                                                    {{-- form= ties this button to its own form, so Enter in a text field still saves. --}}
                                                    <button type="submit" form="fetch-{{ $rssSource->id }}" data-pending-text="Hämtar…" class="mr-auto rounded-lg border border-studio-line px-3 py-1.5 text-[13px] font-semibold hover:border-[#b9b7af] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand disabled:opacity-60">↻ Hämta nu</button>
                                                @endif
                                                <a href="{{ route('admin.superblogs.rss-sources.delete', [$superblog, $rssSource, 'fran' => 'byggaren']) }}" class="text-[13px] font-semibold text-studio-bad underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Ta bort</a>
                                                <button type="submit" class="rounded-lg border border-studio-line px-3 py-1.5 text-[13px] font-semibold hover:border-[#b9b7af] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Spara</button>
                                            </div>
                                        </form>
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

                    <form method="POST" action="{{ route('admin.superblogs.theme.update', $superblog) }}" class="space-y-6">
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
                            {{-- One column: the 390 px panel is too narrow for two cards with six swatches each. --}}
                            <div class="grid gap-4">
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
            </div>
        </section>

        {{-- Hidden below lg, so the form gets the full width on small screens. --}}
        <section aria-label="Förhandsvisning" class="hidden min-w-0 flex-col bg-[#121317] lg:flex lg:min-h-0">
            <div class="flex items-center gap-3 border-b border-studio-line bg-studio-side px-4 py-[9px] text-xs text-studio-muted">
                <span aria-hidden="true" class="tracking-[2px] text-[#b5b3ab]">●●●</span>
                <code class="min-w-0 flex-1 truncate rounded-md bg-studio-bg px-2.5 py-1 text-studio-ink">{{ $superblog->domain ? 'https://'.$superblog->domain : 'Ingen webbadress än' }}</code>
                <span data-preview-label>Dator</span>
            </div>
            <div data-preview-pane class="flex-1 overflow-auto p-5">
                {{-- Without JS the frame fills the pane unscaled. app.js sizes it per device; overflow-hidden clips the space the scaled iframe still takes up. --}}
                <div data-preview-frame class="mx-auto h-full w-full overflow-hidden bg-white shadow-[0_14px_50px_#17181c30]">
                    {{-- A real page in an iframe, so the preview uses the public theme CSS without mixing it into the admin. It reloads with the builder on save and tab change. --}}
                    <iframe src="{{ route('admin.superblogs.preview', $superblog) }}" title="Förhandsvisning av {{ $superblog->name }}" class="block h-full w-full origin-top-left"></iframe>
                </div>
            </div>
        </section>
    </div>
@endsection
