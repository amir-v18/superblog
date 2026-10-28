@extends('layouts.admin')

@section('title', 'Create staff account')

@section('content')
    <section class="mx-auto mt-12 max-w-md space-y-6 rounded-xl border border-studio-line bg-studio-panel p-6 sm:p-8" aria-labelledby="setup-heading">
        <h1 id="setup-heading" class="text-2xl font-semibold tracking-tight">Create a staff account?</h1>
        <p class="text-sm text-studio-muted">This Google account is not linked to an existing Superblog account. You can ask an administrator to map it to an existing account, or create a new staff account that will remain pending until approved.</p>
        <dl class="space-y-3 rounded-lg bg-studio-bg p-4 text-sm">
            <div>
                <dt class="font-medium text-studio-muted">Verified Google email</dt>
                <dd class="mt-1 break-all text-studio-ink">{{ $email }}</dd>
            </div>
            <div>
                <dt class="font-medium text-studio-muted">Google subject</dt>
                <dd class="mt-1 break-all font-mono text-studio-ink">{{ $subject }}</dd>
            </div>
        </dl>
        <p class="text-xs text-studio-muted">The subject is an account identifier, not a password or sign-in token. Share it only with the Superblog operator through a trusted channel.</p>
        <form method="POST" action="{{ route('auth.google.create-account') }}">
            @csrf
            <button type="submit" class="w-full rounded-lg bg-studio-brand px-4 py-3 text-sm font-semibold text-studio-side hover:bg-[#ff9068] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Create pending staff account</button>
        </form>
        <a href="{{ route('login') }}" class="inline-flex text-sm font-medium text-studio-muted underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Cancel</a>
    </section>
@endsection
