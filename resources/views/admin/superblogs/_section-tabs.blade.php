<nav aria-label="Superblog" class="mt-8 border-b border-studio-line">
    <ul class="flex flex-wrap gap-6">
        <li>
            <a href="{{ route('admin.superblogs.show', $superblog) }}" @if ($activeTab === 'overview') aria-current="page" @endif class="inline-block border-b-2 border-transparent px-1 pb-3 text-sm font-medium text-studio-muted hover:text-studio-ink aria-[current=page]:border-studio-brand aria-[current=page]:text-studio-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Översikt</a>
        </li>
        <li>
            <a href="{{ route('admin.superblogs.articles.index', $superblog) }}" @if ($activeTab === 'articles') aria-current="page" @endif class="inline-block border-b-2 border-transparent px-1 pb-3 text-sm font-medium text-studio-muted hover:text-studio-ink aria-[current=page]:border-studio-brand aria-[current=page]:text-studio-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Artiklar</a>
        </li>
        <li>
            <a href="{{ route('admin.superblogs.edit', $superblog) }}" @if ($activeTab === 'settings') aria-current="page" @endif class="inline-block border-b-2 border-transparent px-1 pb-3 text-sm font-medium text-studio-muted hover:text-studio-ink aria-[current=page]:border-studio-brand aria-[current=page]:text-studio-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Inställningar</a>
        </li>
    </ul>
</nav>
