@extends('layouts.admin')

@section('title', 'Ta bort Superblog')

@section('content')
    <h1 class="text-3xl font-semibold tracking-tight">Ta bort {{ $superblog->name }} permanent?</h1>
    @if ($errors->any())
        <div role="alert" class="mt-6 rounded-xl border border-studio-bad/40 bg-studio-panel p-4 text-studio-bad">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <div class="mt-6 rounded-xl border border-studio-bad/40 bg-studio-panel p-6">
        <p>Detta tar permanent bort Superbloggen, personalens tilldelningar, RSS-flöden och RSS-poster samt alla artiklar och deras länk- och medieposter. Åtgärden kan inte ångras. Mediefiler på lagringen tas inte bort.</p>
        <form method="POST" action="{{ route('admin.superblogs.destroy', $superblog) }}" class="mt-6 flex items-center gap-4">
            @csrf
            @method('DELETE')
            <button type="submit" class="rounded-lg bg-studio-bad px-4 py-2 font-medium text-studio-side hover:bg-studio-bad/85 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Ta bort permanent</button>
            <a href="{{ route('admin.superblogs.edit', $superblog) }}" class="text-sm underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Avbryt</a>
        </form>
    </div>
@endsection
