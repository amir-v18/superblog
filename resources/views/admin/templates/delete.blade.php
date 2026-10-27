@extends('layouts.admin')

@section('title', 'Delete template')

@section('content')
    <a href="{{ route('admin.templates.index') }}" class="text-sm underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Back to templates</a>
    <h1 class="mt-4 text-3xl font-semibold tracking-tight">Delete {{ $template->name }}?</h1>
    <div class="mt-6 rounded-xl border border-studio-bad/40 bg-studio-panel p-6">
        @if ($inUse)
            <p role="alert" class="text-studio-bad">This template is in use by one or more Superblogs and cannot be deleted. Change those Superblogs to another template first.</p>
        @else
            <p>This permanently deletes the template. This cannot be undone.</p>
            <form method="POST" action="{{ route('admin.templates.destroy', $template) }}" class="mt-6 flex items-center gap-4">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-lg bg-studio-bad px-4 py-2 font-medium text-studio-side hover:bg-studio-bad/85 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Yes, delete template</button>
                <a href="{{ route('admin.templates.index') }}" class="text-sm underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Cancel</a>
            </form>
        @endif
    </div>
@endsection
