@extends('layouts.admin')

@section('title', 'Delete Superblog')

@section('content')
    <h1 class="text-3xl font-semibold tracking-tight">Delete {{ $superblog->name }}?</h1>
    <div class="mt-6 rounded-xl border border-studio-bad/40 bg-studio-panel p-6">
        <p>This permanently deletes the Superblog and its staff assignments, RSS sources, RSS items, articles, article links, and media records. This cannot be undone.</p>
        <form method="POST" action="{{ route('admin.superblogs.destroy', $superblog) }}" class="mt-6 flex items-center gap-4">
            @csrf
            @method('DELETE')
            <button type="submit" class="rounded-lg bg-studio-bad px-4 py-2 font-medium text-studio-side hover:bg-studio-bad/85 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Yes, delete Superblog</button>
            <a href="{{ route('admin.dashboard') }}" class="text-sm underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Cancel</a>
        </form>
    </div>
@endsection
