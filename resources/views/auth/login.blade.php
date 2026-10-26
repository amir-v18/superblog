@extends('layouts.admin')

@section('title', 'Log in')

@section('content')
    <section class="mx-auto max-w-md rounded-xl border border-studio-line bg-studio-panel p-6 sm:p-8" aria-labelledby="login-heading">
        <h1 id="login-heading" class="text-2xl font-semibold tracking-tight">Log in to Superblog</h1>
        <p class="mt-2 text-sm text-studio-muted">Sign in with your administrator account.</p>

        <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-6">
            @csrf
            <div>
                <label for="email" class="block text-sm font-medium">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" maxlength="255"
                    @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                    class="mt-2 block w-full rounded-lg border border-studio-line bg-studio-bg px-3 py-2 focus:border-studio-brand focus:outline-2 focus:outline-studio-brand">
                @error('email')
                    <p id="email-error" role="alert" class="mt-2 text-sm text-studio-bad">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="password" class="block text-sm font-medium">Password</label>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                    @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                    class="mt-2 block w-full rounded-lg border border-studio-line bg-studio-bg px-3 py-2 focus:border-studio-brand focus:outline-2 focus:outline-studio-brand">
                @error('password')
                    <p id="password-error" role="alert" class="mt-2 text-sm text-studio-bad">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit" class="w-full rounded-lg bg-studio-brand px-4 py-3 text-sm font-semibold text-studio-side hover:bg-[#ff9068] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Log in</button>
        </form>
    </section>
@endsection
