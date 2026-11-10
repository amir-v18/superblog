@extends('layouts.admin')
@use('App\Support\Theme')

@section('title', $superblog->name)

{{-- Full width and, from lg, exactly the window height, so the panel and the preview scroll separately. --}}
@section('main-class', 'flex w-full flex-col lg:h-screen')

@section('content')
    @php
        // Tabs are plain links with ?flik=..., so only the active tab is rendered and no JS is needed.
        // Each implemented step is a query-string tab with its own content branch below.
        $publicationTab = $superblog->status === 'published' ? '6 Avpublicera' : '6 Publicera';
        $tabs = ['grund' => '1 Grund', 'utseende' => '2 Utseende', 'floden' => '3 Flöden', 'innehall' => '4 Innehåll', 'skriv-artikel' => '5 Skriv artikel', 'publicera' => $publicationTab];
        $tab = in_array(request()->query('flik'), array_keys($tabs), true) ? request()->query('flik') : 'grund';
        $tabClasses = 'block whitespace-nowrap border-b-2 border-transparent px-2 py-[13px] text-center text-xs font-semibold text-studio-muted hover:text-studio-ink aria-[current=page]:border-studio-brand aria-[current=page]:text-studio-brand-text focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-studio-brand';
        // The layout's "Nytt magasin" modal posts name, domain and template_id too, and old input is shared by
        // every form on the page. After a failed modal submit, its errors and values belong to the modal only.
        $fromCreateModal = old('_form') === 'create-superblog';
        $old = fn (string $key, mixed $default = null): mixed => $fromCreateModal ? $default : old($key, $default);
        // On wide screens the header starts at the very top, next to the layout's mode switch in the page corner
        // (about 90 px from the right edge). The header keeps 100 px free on the right there, so a long magazine
        // name is cut short ahead of the switch. On narrow screens the sidebar sits above the header.
        $selectedTemplate = $old('template_id', $superblog->template_id);
    @endphp

    <header class="flex flex-wrap items-center gap-4 border-b border-studio-line bg-studio-side px-5 py-3 lg:pr-25">
        <a href="{{ route('admin.dashboard') }}" class="rounded-lg px-3.5 py-2 text-[13px] font-semibold text-studio-muted hover:text-studio-ink studio-focus-ring"><span aria-hidden="true">←</span> Alla magasin</a>
        <div class="flex min-w-0 flex-1 items-center gap-2.5">
            <h1 class="truncate text-lg font-semibold">{{ $superblog->name }}</h1>
            <x-admin.status-pill :live="$superblog->status === 'published'" />
        </div>
    </header>

    <div class="lg:grid lg:min-h-0 lg:flex-1 lg:grid-cols-[390px_minmax(0,1fr)]">
        <section aria-label="Inställningar" class="flex flex-col border-studio-line bg-studio-side lg:min-h-0 lg:border-r">
            <nav aria-label="Byggarens steg" class="border-b border-studio-line">
                <ul class="grid grid-cols-3">
                    @foreach ($tabs as $key => $label)
                        <li>
                            <a href="{{ route('admin.superblogs.edit', [$superblog, 'flik' => $key]) }}" @if ($tab === $key) aria-current="page" @endif class="{{ $tabClasses }}">{{ $label }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <div class="flex flex-col gap-3.5 p-5 lg:flex-1 lg:overflow-y-auto">
                @if ($errors->any() && ! $fromCreateModal)
                    <x-admin.errors />
                @endif

                @if ($tab === 'grund')
                    <form method="POST" action="{{ route('admin.superblogs.update', $superblog) }}" class="space-y-6">
                        @csrf
                        @method('PUT')

                        <h2 class="text-lg font-semibold">Grunduppgifter</h2>

                        <div>
                            <label for="name" class="block text-sm font-medium">Namn</label>
                            <x-admin.input id="name" name="name" data-live-name-source value="{{ $old('name', $superblog->name) }}" required maxlength="255" class="mt-2" />
                        </div>

                        <div>
                            <label for="slug" class="block text-sm font-medium">Slug</label>
                            <x-admin.input id="slug" name="slug" value="{{ $old('slug', $superblog->slug) }}" required maxlength="255" aria-describedby="slug-help" class="mt-2" />
                            <p id="slug-help" class="mt-1 text-sm text-studio-muted">Adressen blir /superblogs/{slug}. Använd små bokstäver och bindestreck. Måste vara unik.</p>
                        </div>

                        <fieldset>
                            <legend class="text-sm font-medium">Mall</legend>
                            @include('admin.superblogs._template-options')
                        </fieldset>

                        <x-admin.domain-input id="domain" :value="$old('domain', $superblog->domain)" help="Skriv bara domänen, utan https://.">
                            Webbadress (valfri)
                        </x-admin.domain-input>

                        <div>
                            <x-admin.button>Spara</x-admin.button>
                        </div>
                    </form>
                    <section aria-labelledby="delete-superblog-heading" class="rounded-lg border border-studio-bad/35 bg-studio-panel p-4">
                        <h3 id="delete-superblog-heading" class="font-semibold text-studio-bad">Ta bort Superblog</h3>
                        <p class="mt-1 text-sm text-studio-muted">Tar permanent bort sajten och dess innehåll.</p>
                        <a href="{{ route('admin.superblogs.delete', $superblog) }}" class="mt-3 inline-flex rounded-lg border border-studio-bad/50 px-3.5 py-2 text-sm font-semibold text-studio-bad hover:bg-studio-bad/10 studio-focus-ring">Ta bort Superblog permanent</a>
                    </section>
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
                            <div role="alert" class="mt-4 rounded-lg border border-studio-bad/40 bg-studio-bad-bg p-4 text-studio-bad">{{ session('error') }}</div>
                        @endif

                        <form method="POST" action="{{ route('admin.superblogs.rss-sources.store', $superblog) }}" class="mt-4 flex flex-col gap-2 rounded-[10px] border border-dashed border-studio-line-dash p-3">
                            @csrf
                            {{-- fran brings the user back to this tab; without it the controller goes to the RSS list. --}}
                            <input type="hidden" name="fran" value="byggaren">
                            {{-- The backend requires is_active, so new feeds are added as active. --}}
                            <input type="hidden" name="is_active" value="1">
                            <h3 class="text-[13px] font-semibold">Lägg till flöde</h3>
                            {{-- A failed card save also flashes name and url, so andrar keeps that text out of this form. --}}
                            <x-admin.input name="name" value="{{ old('andrar') ? '' : $old('name') }}" required maxlength="255" placeholder="Namn, t.ex. Lokalnytt" aria-label="Flödets namn" class="text-sm" />
                            <x-admin.input name="url" type="url" value="{{ old('andrar') ? '' : $old('url') }}" required maxlength="65535" placeholder="https://exempel.se/rss.xml" aria-label="Flödets adress" class="text-sm" />
                            <x-admin.button class="self-end">Lägg till</x-admin.button>
                        </form>

                        <h3 class="mt-5.5 mb-2 text-[13px] font-semibold tracking-[.04em]">Tillagda flöden <small class="ml-1 text-studio-muted">{{ $rssSources->count() }}</small></h3>

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
                                            <x-admin.input form="feed-{{ $rssSource->id }}" name="name" value="{{ $isEditing ? old('name') : $rssSource->name }}" required maxlength="255" aria-label="Flödets namn" class="min-w-0 text-sm font-semibold" />
                                            {{-- A separate form sending the saved values: Enter in a text field saves instead of toggling, and unsaved text is never saved by the toggle. --}}
                                            <form method="POST" action="{{ route('admin.superblogs.rss-sources.update', [$superblog, $rssSource]) }}" class="flex shrink-0">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="fran" value="byggaren">
                                                <input type="hidden" name="name" value="{{ $rssSource->name }}">
                                                <input type="hidden" name="url" value="{{ $rssSource->url }}">
                                                <input type="hidden" name="is_active" value="{{ $rssSource->is_active ? 0 : 1 }}">
                                                <button type="submit" role="switch" aria-checked="{{ $rssSource->is_active ? 'true' : 'false' }}" aria-label="Aktivt flöde: {{ $rssSource->name }}" @class(['relative h-5 w-[34px] rounded-full transition-colors after:absolute after:top-0.5 after:left-0.5 after:size-4 after:rounded-full after:bg-white after:transition-transform studio-focus-ring', 'bg-studio-brand after:translate-x-3.5' => $rssSource->is_active, 'bg-studio-switch-off' => ! $rssSource->is_active])></button>
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
                                            <x-admin.input name="url" type="url" value="{{ $isEditing ? old('url') : $rssSource->url }}" required maxlength="65535" aria-label="Flödets adress" class="text-sm" />
                                            <div class="flex items-center justify-end gap-3">
                                                @if ($rssSource->is_active)
                                                    {{-- form= ties this button to its own form, so Enter in a text field still saves. --}}
                                                    <button type="submit" form="fetch-{{ $rssSource->id }}" data-pending-text="Hämtar…" class="mr-auto rounded-lg border border-studio-line px-3 py-1.5 text-[13px] font-semibold hover:border-studio-line-hover studio-focus-ring disabled:opacity-60">↻ Hämta nu</button>
                                                @endif
                                                <a href="{{ route('admin.superblogs.rss-sources.delete', [$superblog, $rssSource, 'fran' => 'byggaren']) }}" class="text-[13px] font-semibold text-studio-bad underline underline-offset-4 studio-focus-ring">Ta bort</a>
                                                <button type="submit" class="rounded-lg border border-studio-line px-3 py-1.5 text-[13px] font-semibold hover:border-studio-line-hover studio-focus-ring">Spara</button>
                                            </div>
                                        </form>
                                        {{-- The count is stored RSS items, not published articles. --}}
                                        <small class="text-[13px] text-studio-muted">{{ $rssSource->is_active ? 'Aktiv · '.$rssSource->rss_items_count.' '.($rssSource->rss_items_count === 1 ? 'artikel' : 'artiklar') : 'Pausad' }}</small>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                @elseif ($tab === 'innehall')
                    <section aria-labelledby="articles-heading">
                        <h2 id="articles-heading" class="text-lg font-semibold">Innehåll</h2>
                        <p class="mt-1 text-sm text-studio-muted">Välj vilka artiklar som ska ingå i publiceringen. På ett publicerat Superblogg tillämpas urvalet direkt när du sparar. När Superblogget är offline sparas bara urvalet.</p>
                        <div hidden data-article-delete-notice role="status" aria-live="polite" class="fixed top-4 right-4 left-4 z-50 mx-auto max-w-md rounded-lg border border-studio-ok/30 bg-studio-live p-3 text-sm text-studio-ok shadow-xl"></div>

                        @foreach ([
                            ['key' => 'manual', 'label' => 'Manuella artiklar', 'articles' => $manualArticles],
                            ['key' => 'rss', 'label' => 'RSS-artiklar', 'articles' => $rssArticles],
                        ] as $group)
                            @include('admin.superblogs._article-section', ['group' => $group, 'superblog' => $superblog])
                        @endforeach
                    </section>
                @elseif ($tab === 'skriv-artikel')
                    <section aria-labelledby="write-article-heading">
                        <h2 id="write-article-heading" class="text-lg font-semibold">Skriv artikel</h2>
                        <p class="mt-1 text-sm text-studio-muted">Skapa en manuell artikel som sparas som utkast. Den publiceras inte automatiskt.</p>
                        <a href="{{ route('admin.superblogs.articles.create', $superblog) }}" class="mt-4 inline-flex rounded-lg bg-studio-brand px-4 py-2 text-sm font-medium text-studio-on-brand hover:bg-studio-brand-hover studio-focus-ring">Skapa artikel</a>
                        <p class="mt-5 text-sm text-studio-muted">Redigera befintliga artiklar under <a href="{{ route('admin.superblogs.edit', [$superblog, 'flik' => 'innehall']) }}" class="font-medium text-studio-ink underline underline-offset-4 studio-focus-ring">Innehåll</a>. Där visas manuella artiklar tillsammans med RSS-artiklar.</p>
                    </section>
                @elseif ($tab === 'publicera')
                    <section aria-labelledby="publish-heading">
                        <aside aria-label="Tillfällig information om publiceringsinställningar" class="mb-5 rounded-lg border border-studio-line bg-studio-panel p-4">
                            <h2 class="text-sm font-semibold">Slutlig publiceringskonfiguration återstår</h2>
                            <p class="mt-1 text-sm text-studio-muted">Domän, DNS och hosting konfigureras när vi har fått de uppgifter som behövs. Den här delen av inställningarna är tillfällig och ännu inte färdigställd.</p>
                        </aside>

                        @if ($superblog->status === 'published')
                            <h2 id="publish-heading" class="text-lg font-semibold">Avpublicera Superblog</h2>
                            <p class="mt-1 text-sm text-studio-muted">Detta tar hela Superblocket offline. Artiklarna behåller sin publiceringsstatus och sina publiceringsdatum, och förhandsvisningen finns kvar i byggaren.</p>

                            <form method="POST" action="{{ route('admin.superblogs.unpublish', $superblog) }}" class="mt-5">
                                @csrf
                                <button type="submit" class="rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 studio-focus-ring studio-light:border-studio-bad">Avpublicera</button>
                            </form>
                        @else
                            <h2 id="publish-heading" class="text-lg font-semibold">Publicera innehåll</h2>
                            <p class="mt-1 text-sm text-studio-muted">Publicera tillämpar det sparade urvalet från Innehåll. Spara ändrade kryssrutor där innan du fortsätter. Valda utkast publiceras och publicerade artiklar som inte längre är valda dras tillbaka. Superblocket markeras som publicerat.</p>

                            @if ($publicationErrors !== [])
                                @php
                                    $grundFieldNames = ['name' => 'Namn', 'slug' => 'Slug', 'template_id' => 'Mall', 'domain' => 'Domän'];
                                @endphp
                                <aside role="status" class="mt-5 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">
                                    <p class="font-semibold">Komplettera Grund innan du publicerar</p>
                                    <ul class="mt-2 list-inside list-disc">
                                        @foreach ($publicationErrors as $field => $messages)
                                            <li>{{ $grundFieldNames[$field] ?? $field }}</li>
                                        @endforeach
                                    </ul>
                                    <a href="{{ route('admin.superblogs.edit', [$superblog, 'flik' => 'grund']) }}" class="mt-3 inline-block font-medium underline underline-offset-4 studio-focus-ring">Gå till Grund</a>
                                </aside>
                            @endif

                            <dl class="mt-5 grid grid-cols-1 gap-2" aria-label="Publiceringsöversikt">
                                <div class="rounded-lg border border-studio-line bg-studio-panel p-3">
                                    <dt class="text-xs text-studio-muted">Valda artiklar</dt>
                                    <dd class="mt-1 text-xl font-semibold">{{ $publicationSummary['selected'] }}</dd>
                                </div>
                                <div class="rounded-lg border border-studio-line bg-studio-panel p-3">
                                    <dt class="text-xs text-studio-muted">Publiceras nu</dt>
                                    <dd class="mt-1 text-xl font-semibold">{{ $publicationSummary['to_publish'] }}</dd>
                                </div>
                                <div class="rounded-lg border border-studio-line bg-studio-panel p-3">
                                    <dt class="text-xs text-studio-muted">Dras tillbaka</dt>
                                    <dd class="mt-1 text-xl font-semibold">{{ $publicationSummary['to_withdraw'] }}</dd>
                                </div>
                            </dl>

                            <form method="POST" action="{{ route('admin.superblogs.articles.publish-selected', $superblog) }}" class="mt-5">
                                @csrf
                                <button type="submit" @disabled($publicationErrors !== []) class="rounded-lg bg-studio-brand px-4 py-2 text-sm font-semibold text-studio-on-brand hover:bg-studio-brand-hover studio-focus-ring disabled:cursor-not-allowed disabled:opacity-50">Publicera</button>
                            </form>
                        @endif
                    </section>
                @else
                    @php
                        // Theme::nameFor() returns null for a missing or unknown stored name, so nothing is preselected then.
                        $currentTheme = Theme::nameFor($superblog);
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
                            <x-admin.button>Spara</x-admin.button>
                        </div>
                    </form>
                @endif
            </div>
        </section>

        {{-- Stacks below the builder on small screens and shares the remaining grid width on desktop. --}}
        <section aria-label="Förhandsvisning" class="flex h-[70vh] min-w-0 flex-col bg-studio-preview-bg lg:h-auto lg:min-h-0">
            <div class="flex flex-wrap items-center gap-3 border-b border-studio-line bg-studio-side px-4 py-2.25 text-xs text-studio-muted">
                <span aria-hidden="true" class="tracking-[2px] text-studio-preview-dots">●●●</span>
                <code class="min-w-0 flex-1 truncate rounded-md bg-studio-bg px-2.5 py-1 text-studio-ink">{{ $superblog->domain ? 'https://'.$superblog->domain : 'Ingen webbadress än' }}</code>
                <span data-preview-label>Dator</span>
                {{-- Hidden until app.js runs, so the controls only appear when switching the preview works. --}}
                <div role="group" aria-label="Förhandsvisning" hidden data-preview-devices class="inline-flex shrink-0 rounded-lg bg-studio-panel p-0.75">
                    <button type="button" data-preview-device="desktop" aria-pressed="true" class="rounded-md px-3.5 py-1.5 text-xs font-semibold text-studio-muted hover:text-studio-ink aria-pressed:bg-studio-brand aria-pressed:text-studio-on-brand studio-focus-ring">Dator</button>
                    <button type="button" data-preview-device="mobile" aria-pressed="false" class="rounded-md px-3.5 py-1.5 text-xs font-semibold text-studio-muted hover:text-studio-ink aria-pressed:bg-studio-brand aria-pressed:text-studio-on-brand studio-focus-ring">Mobil</button>
                </div>
            </div>
            <div data-preview-pane class="flex flex-1 flex-col overflow-auto p-5">
                {{-- The empty hint lives in the builder, not in the iframe, so it can never end up on the public site. --}}
                @if ($publishedArticlesCount === 0)
                    @php
                        $feedCount = $rssSources->count();
                        $itemCount = $rssSources->sum('rss_items_count');
                    @endphp
                    <div class="mx-auto mb-3.5 w-full max-w-190 shrink-0 rounded-[10px] border border-studio-line bg-studio-panel px-3.5 py-2.75 text-[13px] leading-[1.45] text-studio-muted">
                        <b class="text-studio-ink">Tomt magasin.</b>
                        @if ($feedCount === 0)
                            Lägg till ett RSS-flöde för att komma igång.
                        @else
                            {{ $feedCount }} {{ $feedCount === 1 ? 'flöde' : 'flöden' }} · {{ $itemCount }} {{ $itemCount === 1 ? 'hämtad post' : 'hämtade poster' }} · 0 publicerade artiklar. Platshållarna fylls när artiklar publiceras.
                        @endif
                        <a href="{{ route('admin.superblogs.edit', [$superblog, 'flik' => 'floden']) }}" class="ml-1.5 font-bold text-studio-brand-text studio-focus-ring">Lägg till flöde →</a>
                    </div>
                @endif
                {{-- Without JS the frame fills the pane unscaled. app.js sizes it per device; overflow-hidden clips the space the scaled iframe still takes up. --}}
                <div data-preview-frame class="mx-auto min-h-0 w-full flex-1 overflow-hidden bg-white shadow-[0_14px_50px_var(--color-studio-preview-shadow)]">
                    {{-- A real page in an iframe, so the preview uses the public theme CSS without mixing it into the admin. It reloads with the builder on save and tab change. --}}
                    <iframe src="{{ route('admin.superblogs.preview', $superblog) }}" title="Förhandsvisning av {{ $superblog->name }}" class="block h-full w-full origin-top-left"></iframe>
                </div>
            </div>
        </section>
    </div>
@endsection
