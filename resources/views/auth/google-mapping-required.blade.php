@extends('layouts.admin')

@section('title', 'Administrator setup required')

@section('content')
    <section class="mx-auto max-w-xl rounded-xl border border-slate-200 bg-white p-6 sm:p-8" aria-labelledby="mapping-heading">
        <h1 id="mapping-heading" class="text-2xl font-semibold tracking-tight">Administrator setup required</h1>
        <p class="mt-3 text-sm text-slate-600">This verified Google account needs administrator setup before it can be used. Ask your administrator to verify and map this Google subject to the intended existing account. No account access or privileges were changed.</p>
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
        <p class="mt-4 text-xs text-slate-500">The subject identifies this Google account; it is not a password or sign-in token. Share it only with the Superblog operator through a trusted channel.</p>
        <a href="{{ route('login') }}" class="mt-6 inline-flex rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Back to sign in</a>
    </section>
@endsection
