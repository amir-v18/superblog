@extends('layouts.admin')

@section('title', 'Delete staff account')

@section('content')
    <a href="{{ route('admin.staff.index') }}" class="text-sm underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Back to Staff Management</a>
    <h1 class="mt-4 text-3xl font-semibold tracking-tight">Delete {{ $user->name }}?</h1>
    <p class="mt-2 text-studio-muted">{{ $user->email }}</p>
    <div class="mt-6 rounded-xl border border-studio-bad/40 bg-studio-panel p-6">
        <p>This permanently deletes this staff account and its Superblog assignments and active database sessions. This cannot be undone.</p>
        <form method="POST" action="{{ route('admin.staff.destroy', $user) }}" class="mt-6 flex items-center gap-4">
            @csrf
            @method('DELETE')
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-studio-bad px-4 py-2 font-medium text-studio-side hover:bg-studio-bad/85 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">
                <svg aria-hidden="true" class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 6h18M8 6V4h8v2m3 0-1 14H6L5 6m4 4v6m6-6v6" />
                </svg>
                <span>Yes, permanently delete account</span>
            </button>
            <a href="{{ route('admin.staff.index') }}" class="text-sm underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Cancel</a>
        </form>
    </div>
@endsection
