@if ($paginator->hasPages())
    @php
        $lastPage = $paginator->lastPage();
        $currentPage = $paginator->currentPage();

        if ($lastPage <= 7) {
            $pageNumbers = range(1, $lastPage);
        } elseif ($currentPage <= 4) {
            $pageNumbers = [1, 2, 3, 4, 'ellipsis', $lastPage];
        } elseif ($currentPage >= $lastPage - 2) {
            $pageNumbers = [1, 'ellipsis', $lastPage - 2, $lastPage - 1, $lastPage];
        } else {
            $pageNumbers = [1, 'ellipsis', $currentPage - 1, $currentPage, $currentPage + 1, 'ellipsis', $lastPage];
        }
    @endphp

    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex w-full min-w-0 max-w-full flex-wrap items-center gap-1">
        @if ($paginator->onFirstPage())
            <span aria-disabled="true" class="inline-flex min-h-8 min-w-8 shrink-0 items-center justify-center rounded border border-studio-line px-2 py-1.5 text-sm text-studio-muted" aria-label="{{ __('pagination.previous') }}">‹</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" data-pagination-section="{{ $sectionKey }}" rel="prev" class="inline-flex min-h-8 min-w-8 shrink-0 items-center justify-center rounded border border-studio-line px-2 py-1.5 text-sm text-studio-ink hover:bg-studio-panel studio-focus-ring" aria-label="{{ __('pagination.previous') }}">‹</a>
        @endif

        @foreach ($pageNumbers as $page)
            @if ($page === 'ellipsis')
                <span aria-hidden="true" class="px-0.5 text-xs text-studio-muted">…</span>
            @else
                @if ($page === $currentPage)
                    <span aria-current="page" class="inline-flex min-h-8 min-w-8 shrink-0 items-center justify-center rounded border-2 border-studio-brand bg-studio-brand px-2 py-1.5 text-sm font-bold text-studio-side shadow-sm">{{ $page }}</span>
                @else
                    <a href="{{ $paginator->url($page) }}" data-pagination-section="{{ $sectionKey }}" class="inline-flex min-h-8 min-w-8 shrink-0 items-center justify-center rounded border border-studio-line px-2 py-1.5 text-sm text-studio-ink hover:bg-studio-panel studio-focus-ring" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                @endif
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" data-pagination-section="{{ $sectionKey }}" rel="next" class="inline-flex min-h-8 min-w-8 shrink-0 items-center justify-center rounded border border-studio-line px-2 py-1.5 text-sm text-studio-ink hover:bg-studio-panel studio-focus-ring" aria-label="{{ __('pagination.next') }}">›</a>
        @else
            <span aria-disabled="true" class="inline-flex min-h-8 min-w-8 shrink-0 items-center justify-center rounded border border-studio-line px-2 py-1.5 text-sm text-studio-muted" aria-label="{{ __('pagination.next') }}">›</span>
        @endif
    </nav>
@endif
