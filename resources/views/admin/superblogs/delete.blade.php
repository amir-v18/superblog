@extends('layouts.admin')

@section('title', 'Delete Superblog')

@section('content')
    <h1 class="text-3xl font-semibold tracking-tight">Delete {{ $superblog->name }}?</h1>
    <div class="mt-6 rounded-xl border border-red-200 bg-white p-6">
        <p>This permanently deletes the Superblog and its staff assignments, RSS sources, RSS items, articles, article links, and media records. This cannot be undone.</p>
        <form method="POST" action="{{ route('admin.superblogs.destroy', $superblog) }}" class="mt-6 flex items-center gap-4">
            @csrf
            @method('DELETE')
            <button type="submit" class="rounded-lg bg-red-700 px-4 py-2 font-medium text-white hover:bg-red-800">Yes, delete Superblog</button>
            <a href="{{ route('admin.dashboard') }}" class="text-sm underline underline-offset-4">Cancel</a>
        </form>
    </div>
@endsection
