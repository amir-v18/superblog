@extends('layouts.admin')

@section('title', 'Create staff account')

@section('content')
    <section class="mx-auto max-w-xl rounded-xl border border-slate-200 bg-white p-6 sm:p-8" aria-labelledby="setup-heading">
        <h1 id="setup-heading" class="text-2xl font-semibold tracking-tight">Create a staff account?</h1>
        <p class="mt-3 text-sm text-slate-600">This Google account is not linked to an existing Superblog account. You can ask an administrator to map it to an existing account, or create a new staff account that will remain pending until approved.</p>
        <dl class="mt-6 space-y-3 rounded-lg bg-slate-50 p-4 text-sm">
            <div>
                <dt class="font-medium text-slate-700">Verified Google email</dt>
                <dd class="mt-1 break-all text-slate-900">{{ $email }}</dd>
            </div>
            <div>
                <dt class="font-medium text-slate-700">Google subject</dt>
                <dd class="mt-1 break-all font-mono text-slate-900">{{ $subject }}</dd>
            </div>
        </dl>
        <p class="mt-4 text-xs text-slate-500">The subject is an account identifier, not a password or sign-in token. Share it only with the Superblog operator through a trusted channel.</p>
        <form method="POST" action="{{ route('auth.google.create-account') }}" class="mt-6">
            @csrf
            <button type="submit" class="w-full rounded-lg bg-slate-900 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900">Create pending staff account</button>
        </form>
        <a href="{{ route('login') }}" class="mt-4 inline-flex text-sm font-medium text-slate-700 underline">Cancel</a>
    </section>
@endsection
