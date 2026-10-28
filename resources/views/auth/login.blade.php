@extends('layouts.admin')

@section('title', 'Log in')

@section('content')
    <section class="mx-auto max-w-md rounded-xl border border-studio-line bg-studio-panel mt-12 space-y-6 p-6 sm:p-8" aria-labelledby="login-heading">
        <h1 id="login-heading" class="text-2xl font-semibold tracking-tight">Sign in to Superblog</h1>
        <p class="mt-2 text-sm text-studio-muted">Sign in with Google. New staff accounts remain pending until approved.</p>

        @if (session('error'))
            <p role="alert" class="mt-5 rounded-lg bg-studio-brand p-3 text-sm text-red-800">{{ session('error') }}</p>
        @endif

        <a href="{{ route('auth.google.redirect') }}" class="mt-8 flex w-full items-center justify-center rounded-lg border border-studio-line bg-studio-brand px-4 py-3 text-sm font-semibold text-slate-900 hover:bg-[#ff9068] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900">
            <svg aria-hidden="true" class="mr-3 h-5 w-5" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5Z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.74 7.18l7.72 5.99c4.51-4.16 7.06-10.29 7.06-17.64Z"/><path fill="#FBBC05" d="M10.53 28.59A14.4 14.4 0 0 1 9.75 24c0-1.59.27-3.12.76-4.59l-7.98-6.19A23.9 23.9 0 0 0 0 24c0 3.87.93 7.53 2.56 10.78l7.97-6.19Z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.91-5.81l-7.72-5.99c-2.14 1.45-4.89 2.3-8.19 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48Z"/></svg>
            Sign in with Google
        </a>
    </section>
@endsection
