@extends('layouts.admin')

@section('title', 'Templates')

@section('content')
    <a href="{{ route('admin.dashboard') }}" class="text-sm underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Back to dashboard</a>
    <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight">Templates</h1>
            <p class="mt-2 text-studio-muted">Manage the layouts available to Superblogs.</p>
        </div>
        <a href="{{ route('admin.templates.create') }}" class="rounded-lg bg-studio-brand px-4 py-2 text-sm font-medium text-studio-side hover:bg-[#ff9068] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Create template</a>
    </div>

    @if ($errors->any())
        <div role="alert" class="mt-6 rounded-lg border border-studio-bad/40 bg-[#43261c] p-4 text-studio-bad">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <section class="mt-8 rounded-xl border border-studio-line bg-studio-panel p-6" aria-labelledby="templates-heading">
        <h2 id="templates-heading" class="text-lg font-semibold">Template catalog</h2>
        <ul class="mt-4 divide-y divide-studio-line">
            @forelse ($templates as $template)
                <li class="flex flex-wrap items-center justify-between gap-4 py-4">
                    <div>
                        <h3 class="font-medium">{{ $template->name }}</h3>
                        <p class="mt-1 break-all text-sm text-studio-muted">{{ $template->slug }} · {{ $template->layout }}</p>
                        <p class="mt-1 text-sm text-studio-muted">{{ $template->is_active ? 'Active' : 'Inactive' }} · Used by {{ $template->superblogs_count }} Superblogs</p>
                    </div>
                    <div class="flex gap-4 text-sm font-medium">
                        <a href="{{ route('admin.templates.edit', $template) }}" class="underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Edit</a>
                        @if ($template->superblogs_count === 0)
                            <a href="{{ route('admin.templates.delete', $template) }}" class="text-studio-bad underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Delete</a>
                        @else
                            <span class="text-studio-muted">In use</span>
                        @endif
                    </div>
                </li>
            @empty
                <li class="py-4 text-sm text-studio-muted">No templates exist yet. Create one before adding a Superblog.</li>
            @endforelse
        </ul>
        <div class="mt-4">{{ $templates->links() }}</div>
    </section>
@endsection
