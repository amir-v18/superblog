@extends('layouts.admin')

@section('title', 'Awaiting approval')

@section('content')
    <section class="mx-auto max-w-md rounded-xl border border-slate-200 bg-white p-6 sm:p-8" aria-labelledby="approval-heading">
        <h1 id="approval-heading" class="text-2xl font-semibold tracking-tight">Your account is awaiting approval</h1>
        <p class="mt-4 text-slate-600">You’re signed in as <span class="break-all font-medium text-slate-900">{{ auth()->user()->email }}</span>.</p>
        <p class="mt-4 text-slate-600">An administrator needs to approve your account before you can access the dashboard and manage Superblogs.</p>
        <p class="mt-4 text-sm text-slate-600">Please check back later. You can use the Log out button above to sign out or switch accounts.</p>
    </section>
@endsection
