@extends('layouts.admin')

@section('title', $superblog->exists ? 'Edit Superblog' : 'Create Superblog')

@section('content')
    <a href="{{ route('admin.dashboard') }}" class="text-sm underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Back to dashboard</a>
    <h1 class="mt-4 text-3xl font-semibold tracking-tight">{{ $superblog->exists ? 'Edit Superblog' : 'Create Superblog' }}</h1>

    @if ($errors->any())
        <div role="alert" class="mt-6 rounded-lg border border-studio-bad/40 bg-[#43261c] p-4 text-studio-bad">
            <p class="font-medium">Please correct the following:</p>
            <ul class="mt-2 list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($templates->isEmpty())
        <p role="status" class="mt-6 rounded-lg border border-[#4a4430] bg-[#2c2a24] p-4 text-[#e2d6a8]">
            No templates are available. A template must be added before you can save a Superblog.
            @can('viewAny', App\Models\Template::class)
                <a href="{{ route('admin.templates.index') }}" class="font-medium underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Manage templates</a>
            @endcan
        </p>
    @endif

    @if ($superblog->exists)
        <p class="mt-4"><a href="{{ route('admin.superblogs.rss-sources.index', $superblog) }}" class="font-medium underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Manage RSS sources</a></p>
    @endif

    <form method="POST" action="{{ $superblog->exists ? route('admin.superblogs.update', $superblog) : route('admin.superblogs.store') }}" class="mt-6 space-y-5 rounded-xl border border-studio-line bg-studio-panel p-6">
        @csrf
        @if ($superblog->exists)
            @method('PUT')
        @endif
        <div>
            <label for="name" class="block text-sm font-medium">Name</label>
            <input id="name" name="name" value="{{ old('name', $superblog->name) }}" required maxlength="255" class="mt-2 w-full rounded-lg border border-studio-line bg-studio-bg p-3 focus-visible:outline-2 focus-visible:outline-studio-brand">
        </div>
        <div>
            <label for="slug" class="block text-sm font-medium">Slug</label>
            <input id="slug" name="slug" value="{{ old('slug', $superblog->slug) }}" maxlength="255" aria-describedby="slug-help" class="mt-2 w-full rounded-lg border border-studio-line bg-studio-bg p-3 focus-visible:outline-2 focus-visible:outline-studio-brand">
            <p id="slug-help" class="mt-1 text-sm text-studio-muted">Use lowercase words separated by hyphens. Leave blank to generate from the name. Must be unique across all Superblogs.</p>
        </div>
        <div>
            <label for="domain" class="block text-sm font-medium">Domain (optional)</label>
            <input id="domain" name="domain" value="{{ old('domain', $superblog->domain) }}" maxlength="253" placeholder="blog.example.com" aria-describedby="domain-help" class="mt-2 w-full rounded-lg border border-studio-line bg-studio-bg p-3 focus-visible:outline-2 focus-visible:outline-studio-brand">
            <p id="domain-help" class="mt-1 text-sm text-studio-muted">Enter a hostname without https://, a port, or a path. Use punycode for international domains.</p>
        </div>
        <div>
            <label for="template_id" class="block text-sm font-medium">Template</label>
            <select id="template_id" name="template_id" required @disabled($templates->isEmpty()) class="mt-2 w-full rounded-lg border border-studio-line bg-studio-bg p-3 focus-visible:outline-2 focus-visible:outline-studio-brand">
                <option value="">Select a template</option>
                @foreach ($templates as $template)
                    <option value="{{ $template->id }}" @selected((string) old('template_id', $superblog->template_id) === (string) $template->id)>{{ $template->name }}{{ $template->is_active ? '' : ' (inactive)' }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="description" class="block text-sm font-medium">Description (optional)</label>
            <textarea id="description" name="description" rows="5" class="mt-2 w-full rounded-lg border border-studio-line bg-studio-bg p-3 focus-visible:outline-2 focus-visible:outline-studio-brand">{{ old('description', $superblog->description) }}</textarea>
        </div>
        <div class="flex items-center gap-4">
            <button type="submit" @disabled($templates->isEmpty()) class="rounded-lg bg-studio-brand px-4 py-2 font-medium text-studio-side hover:bg-[#ff9068] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand disabled:cursor-not-allowed disabled:opacity-50">Save Superblog</button>
            <a href="{{ route('admin.dashboard') }}" class="text-sm underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Cancel</a>
        </div>
    </form>
@endsection
