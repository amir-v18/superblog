@extends('layouts.admin')

@section('title', 'Templates')

@section('content')
    <a href="{{ route('admin.dashboard') }}" class="text-sm underline underline-offset-4">Back to dashboard</a>
    <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight">Templates</h1>
            <p class="mt-2 text-slate-600">Manage the layouts available to Superblogs.</p>
        </div>
        <a href="{{ route('admin.templates.create') }}" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Create template</a>
    </div>

    @if ($errors->any())
        <div role="alert" class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4 text-red-900">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <section class="mt-8 rounded-xl border border-slate-200 bg-white p-6" aria-labelledby="templates-heading">
        <h2 id="templates-heading" class="text-lg font-semibold">Template catalog</h2>
        <ul class="mt-4 divide-y divide-slate-200">
            @forelse ($templates as $template)
                <li class="flex flex-wrap items-center justify-between gap-4 py-4">
                    <div>
                        <h3 class="font-medium">{{ $template->name }}</h3>
                        <p class="mt-1 break-all text-sm text-slate-600">{{ $template->slug }} · {{ $template->layout }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ $template->is_active ? 'Active' : 'Inactive' }} · Used by {{ $template->superblogs_count }} Superblogs</p>
                    </div>
                    <div class="flex gap-4 text-sm font-medium">
                        <a href="{{ route('admin.templates.edit', $template) }}" class="underline underline-offset-4">Edit</a>
                        @if ($template->superblogs_count === 0)
                            <a href="{{ route('admin.templates.delete', $template) }}" class="text-red-700 underline underline-offset-4">Delete</a>
                        @else
                            <span class="text-slate-500">In use</span>
                        @endif
                    </div>
                </li>
            @empty
                <li class="py-4 text-sm text-slate-600">No templates exist yet. Create one before adding a Superblog.</li>
            @endforelse
        </ul>
        <div class="mt-4">{{ $templates->links() }}</div>
    </section>
@endsection
