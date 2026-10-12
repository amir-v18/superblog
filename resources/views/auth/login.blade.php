@extends('layouts.admin')

@section('title', 'Log in')

@section('content')
    <section class="mx-auto max-w-md rounded-xl border border-slate-200 bg-white p-6 sm:p-8" aria-labelledby="login-heading">
        <h1 id="login-heading" class="text-2xl font-semibold tracking-tight">Log in to Superblog</h1>
        <p class="mt-2 text-sm text-slate-600">Sign in with your administrator account.</p>

        <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-6">
            @csrf
            <div>
                <label for="email" class="block text-sm font-medium">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" maxlength="255"
                    @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                    class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-slate-900 focus:outline-2 focus:outline-slate-900">
                @error('email')
                    <p id="email-error" role="alert" class="mt-2 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="password" class="block text-sm font-medium">Password</label>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                    @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                    class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-slate-900 focus:outline-2 focus:outline-slate-900">
                @error('password')
                    <p id="password-error" role="alert" class="mt-2 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit" class="w-full rounded-lg bg-slate-900 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900">Log in</button>
        </form>
    </section>
@endsection
