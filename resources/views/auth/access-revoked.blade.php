@extends('layouts.admin')

@section('title', 'Access revoked')

@section('content')
    <section class="mx-auto max-w-md rounded-xl border border-studio-line bg-studio-panel p-6 sm:p-8" aria-labelledby="access-revoked-heading">
        <h1 id="access-revoked-heading" class="text-2xl font-semibold tracking-tight">Your access has been revoked</h1>
        <p class="mt-4 text-studio-muted">You’re signed in as <span class="break-all font-medium text-studio-ink">{{ auth()->user()->email }}</span>.</p>
        <p class="mt-4 text-studio-muted">Your account can no longer access the admin area. Please contact your administrator if you believe this is a mistake.</p>
        <p class="mt-4 text-sm text-studio-muted">You can use the Log out button above to sign out or switch accounts.</p>
    </section>
@endsection
