@extends('layouts.admin')

@section('title', 'Articles')

@section('content')
    @isset($superblog)
        @include('admin.superblogs._section-tabs', ['activeTab' => 'articles'])
    @endisset

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight">{{ isset($superblog) ? $superblog->name.' · Artiklar' : 'Articles' }}</h1>
            <p class="mt-2 text-studio-muted">{{ isset($superblog) ? 'Articles belonging to this Superblog.' : 'Articles from the Superblogs you can access.' }}</p>
        </div>
    </div>

    <section class="mt-8 overflow-hidden rounded-xl border border-studio-line bg-studio-panel" aria-label="Articles">
        @if ($articles->isEmpty())
            <p class="p-6 text-sm text-studio-muted">No articles yet. Create a draft from a downloaded RSS item to see it here.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-170 text-left text-sm">
                    <thead class="border-b border-studio-line text-xs uppercase tracking-wide text-studio-muted">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-medium">Title</th>
                            <th scope="col" class="px-5 py-3 font-medium">Superblog</th>
                            <th scope="col" class="px-5 py-3 font-medium">Status</th>
                            <th scope="col" class="px-5 py-3 font-medium">Source</th>
                            <th scope="col" class="px-5 py-3 font-medium">Last updated</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-studio-line">
                        @foreach ($articles as $article)
                            <tr>
                                <td class="px-5 py-4 font-medium">
                                    <a href="{{ route('admin.superblogs.articles.edit', [$article->superblog, $article]) }}" class="underline decoration-studio-line underline-offset-4 hover:decoration-current focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">
                                        {{ $article->title }}
                                    </a>
                                </td>
                                <td class="px-5 py-4 text-studio-muted">{{ $article->superblog->name }}</td>
                                <td class="px-5 py-4">{{ ucfirst($article->status) }}</td>
                                <td class="px-5 py-4">{{ strtoupper($article->source_type) }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-studio-muted">{{ $article->updated_at?->format('M j, Y H:i') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-studio-line px-5 py-4">{{ $articles->links() }}</div>
        @endif
    </section>
@endsection
