@extends('layouts.admin')

@section('title', $template->exists ? 'Edit template' : 'Create template')

@section('content')
    <a href="{{ route('admin.templates.index') }}" class="text-sm underline underline-offset-4">Back to templates</a>
    <h1 class="mt-4 text-3xl font-semibold tracking-tight">{{ $template->exists ? 'Edit template' : 'Create template' }}</h1>

    @if ($errors->any())
        <div role="alert" class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4 text-red-900">
            <p class="font-medium">Please correct the following:</p>
            <ul class="mt-2 list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $template->exists ? route('admin.templates.update', $template) : route('admin.templates.store') }}" class="mt-6 space-y-5 rounded-xl border border-slate-200 bg-white p-6">
        @csrf
        @if ($template->exists)
            @method('PUT')
        @endif
        <div>
            <label for="name" class="block text-sm font-medium">Name</label>
            <input id="name" name="name" value="{{ old('name', $template->name) }}" required maxlength="255" class="mt-2 w-full rounded-lg border border-slate-300 p-3">
        </div>
        <div>
            <label for="slug" class="block text-sm font-medium">Slug</label>
            <input id="slug" name="slug" value="{{ old('slug', $template->slug) }}" maxlength="255" aria-describedby="slug-help" class="mt-2 w-full rounded-lg border border-slate-300 p-3">
            <p id="slug-help" class="mt-1 text-sm text-slate-600">Leave blank to generate a slug from the name.</p>
        </div>
        <div>
            <label for="description" class="block text-sm font-medium">Description (optional)</label>
            <textarea id="description" name="description" rows="4" class="mt-2 w-full rounded-lg border border-slate-300 p-3">{{ old('description', $template->description) }}</textarea>
        </div>
        <div>
            <label for="layout" class="block text-sm font-medium">Public layout</label>
            <select id="layout" name="layout" required class="mt-2 w-full rounded-lg border border-slate-300 p-3">
                @foreach (App\Models\Template::availableLayouts() as $layout => $label)
                    <option value="{{ $layout }}" @selected(old('layout', $template->layout ?: 'layouts.public') === $layout)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <input type="hidden" name="is_active" value="0">
            <label class="flex items-center gap-3 text-sm font-medium">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $template->exists ? $template->is_active : true)) class="rounded border-slate-300">
                Active and available for new Superblogs
            </label>
        </div>
        <div class="flex items-center gap-4">
            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-700">Save template</button>
            <a href="{{ route('admin.templates.index') }}" class="text-sm underline underline-offset-4">Cancel</a>
        </div>
    </form>
@endsection
