<section id="{{ $group['key'] }}-articles-section" data-article-section="{{ $group['key'] }}" data-page-name="{{ $group['key'] === 'manual' ? 'manual_page' : 'rss_page' }}" data-current-page="{{ $group['articles']->currentPage() }}" aria-labelledby="{{ $group['key'] }}-articles-heading" class="mt-6">
    <h3 id="{{ $group['key'] }}-articles-heading" class="text-sm font-semibold">{{ $group['label'] }}</h3>

    @if ($group['articles']->isEmpty())
        <p class="mt-3 rounded-lg border border-dashed border-studio-line p-4 text-sm text-studio-muted">
            @if ($group['key'] === 'manual')
                Inga manuella artiklar ännu.
            @else
                Inga RSS-artiklar ännu. De visas här när RSS-flöden har hämtats.
            @endif
        </p>
    @else
        @php
            $selectionFormId = $group['key'].'-article-selection-form';
            $manualPage = request()->query('manual_page', 1);
            $rssPage = request()->query('rss_page', 1);
        @endphp
        <form id="{{ $selectionFormId }}" method="POST" action="{{ route('admin.superblogs.articles.selection.update', [$superblog, 'flik' => 'innehall', 'manual_page' => $manualPage, 'rss_page' => $rssPage]) }}" class="mt-3">
            @csrf
            @method('PUT')
        </form>

        <ul class="mt-3 flex flex-col gap-2.5">
            @foreach ($group['articles'] as $article)
                <li class="rounded-lg border border-studio-line bg-studio-panel p-3">
                    <input form="{{ $selectionFormId }}" type="hidden" name="visible_article_ids[]" value="{{ $article->id }}">
                    <div class="flex items-start gap-3">
                        <button type="button" data-open-dialog="delete-article-{{ $article->id }}" aria-label="Ta bort {{ $article->title }} permanent" class="mt-0.5 inline-flex size-7 shrink-0 items-center justify-center rounded-md text-studio-bad hover:bg-studio-bad/12 studio-focus-ring">
                            <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" class="size-4" xmlns="http://www.w3.org/2000/svg"><path d="M3.5 5.5h13m-11.5 0 .7 11h8.6l.7-11M7.5 5.5V3.75h5V5.5m-4.25 3v5m3.5-5v5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"/></svg>
                        </button>
                        <label class="flex min-w-0 flex-1 items-start gap-3">
                            <input form="{{ $selectionFormId }}" type="checkbox" name="selected_article_ids[]" value="{{ $article->id }}" @checked($article->is_selected_for_publication) class="mt-1 size-4 shrink-0 accent-studio-brand">
                            <span class="min-w-0">
                                <a href="{{ route('admin.superblogs.articles.edit', [$superblog, $article]) }}" class="wrap-break-word font-medium underline decoration-studio-line underline-offset-4 hover:decoration-current">{{ $article->title }}</a>
                                <span class="mt-1 flex flex-wrap gap-x-2 text-xs text-studio-muted">
                                    <span>{{ $article->source_type === 'rss' ? 'RSS' : 'Manuell' }}</span>
                                </span>
                            </span>
                        </label>
                    </div>

                    <dialog id="delete-article-{{ $article->id }}" aria-labelledby="delete-article-heading-{{ $article->id }}" class="m-auto w-[calc(100%-2rem)] max-w-md rounded-xl border border-studio-bad/40 bg-studio-panel p-0 text-studio-ink shadow-xl backdrop:bg-black/60">
                        <div class="p-5">
                            <h4 id="delete-article-heading-{{ $article->id }}" class="text-lg font-semibold">Ta bort artikeln permanent?</h4>
                            <p class="mt-2 break-words text-sm text-studio-muted">“{{ $article->title }}” tas bort och kan inte återskapas. {{ $article->source_type === 'rss' ? 'Den ursprungliga RSS-posten och RSS-källan finns kvar.' : '' }}</p>
                            <form method="POST" action="{{ route('admin.superblogs.articles.destroy', [$superblog, $article, 'flik' => 'innehall', 'manual_page' => $manualPage, 'rss_page' => $rssPage]) }}" data-article-delete-form class="mt-5 flex flex-wrap items-center justify-end gap-3">
                                @csrf
                                @method('DELETE')
                                <button type="button" data-close-dialog class="rounded-lg border border-studio-line px-3.5 py-2 text-sm font-medium hover:border-[#b9b7af] studio-focus-ring">Avbryt</button>
                                <button type="submit" class="rounded-lg bg-studio-bad px-3.5 py-2 text-sm font-semibold text-studio-side hover:bg-studio-bad/85 studio-focus-ring">Ta bort permanent</button>
                            </form>
                        </div>
                    </dialog>
                </li>
            @endforeach
        </ul>

        @if ($group['articles']->hasPages())
            <div class="mt-3 w-full min-w-0 max-w-full overflow-hidden">{{ $group['articles']->links('vendor.pagination.contents', ['sectionKey' => $group['key']]) }}</div>
        @endif

        <x-admin.button form="{{ $selectionFormId }}" class="mt-4">Spara urval</x-admin.button>
    @endif
</section>
