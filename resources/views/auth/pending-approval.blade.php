@extends('layouts.admin')

@section('title', 'Awaiting approval')

@section('content')
    <section class="mx-auto max-w-md rounded-xl border border-studio-line bg-studio-panel p-6 sm:p-8" aria-labelledby="approval-heading">
        <h1 id="approval-heading" class="text-2xl font-semibold tracking-tight">Your account is awaiting approval</h1>
        <p class="mt-4 text-studio-muted">You’re signed in as <span class="break-all font-medium text-studio-ink">{{ auth()->user()->email }}</span>.</p>
        <p class="mt-4 text-studio-muted">An administrator needs to approve your account before you can access the dashboard and manage Superblogs.</p>
        <p class="mt-4 text-sm text-studio-muted">Please check back later. You can use the Log out button above to sign out or switch accounts.</p>
    </section>
@endsection
