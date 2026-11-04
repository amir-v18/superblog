@extends('layouts.admin')

@section('title', 'Publicerade')
@section('main-class', 'w-full max-w-[1180px] px-[clamp(20px,4vw,56px)] py-10')

@section('content')
    <div>
        <p class="text-xs font-semibold uppercase tracking-widest text-studio-muted">Publicerade</p>
        <h1 class="mt-1 text-[34px] font-semibold tracking-tight">Publicerade Superblogs</h1>
        <p class="mt-2 text-studio-muted">Live Superblogs som du har tillgång till.</p>
    </div>

    @if ($superblogs->isEmpty())
        <p class="mt-8 rounded-xl border border-studio-line bg-studio-panel p-6 text-sm text-studio-muted">Inga publicerade Superblogs ännu.</p>
    @else
        <ul class="mt-8 grid grid-cols-[repeat(auto-fill,minmax(300px,1fr))] gap-4.5">
            @foreach ($superblogs as $superblog)
                <li><x-admin.superblog-card :superblog="$superblog" /></li>
            @endforeach
        </ul>
        @if ($superblogs->hasPages())
            <div class="mt-6">{{ $superblogs->links() }}</div>
        @endif
    @endif
@endsection
