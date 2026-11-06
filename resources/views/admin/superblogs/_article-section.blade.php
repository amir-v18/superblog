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
        <form method="POST" action="{{ route('admin.superblogs.articles.selection.update', [$superblog, 'flik' => 'innehall', 'manual_page' => request()->query('manual_page', 1), 'rss_page' => request()->query('rss_page', 1)]) }}" class="mt-3">
            @csrf
            @method('PUT')

            <ul class="flex flex-col gap-2.5">
                @foreach ($group['articles'] as $article)
                    <li class="rounded-lg border border-studio-line bg-studio-panel p-3">
                        <input type="hidden" name="visible_article_ids[]" value="{{ $article->id }}">
                        <label class="flex items-start gap-3">
                            <input type="checkbox" name="selected_article_ids[]" value="{{ $article->id }}" @checked($article->is_selected_for_publication) class="mt-1 size-4 shrink-0 accent-studio-brand">
                            <span class="min-w-0">
                                <a href="{{ route('admin.superblogs.articles.edit', [$superblog, $article]) }}" class="wrap-break-word font-medium underline decoration-studio-line underline-offset-4 hover:decoration-current">{{ $article->title }}</a>
                                <span class="mt-1 flex flex-wrap gap-x-2 text-xs text-studio-muted">
                                    <span>{{ $article->source_type === 'rss' ? 'RSS' : 'Manuell' }}</span>
                                </span>
                            </span>
                        </label>
                    </li>
                @endforeach
            </ul>

            @if ($group['articles']->hasPages())
                <div class="mt-3 w-full min-w-0 max-w-full overflow-hidden">{{ $group['articles']->links('vendor.pagination.contents', ['sectionKey' => $group['key']]) }}</div>
            @endif

            <x-admin.button class="mt-4">Spara urval</x-admin.button>
        </form>
    @endif
</section>
