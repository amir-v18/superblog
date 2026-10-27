@extends('layouts.admin')

@section('title', 'Översikt')

{{-- Figma's .dash: left-aligned, max 1180px, padding 40px clamp(20px, 4vw, 56px). --}}
@section('main-class', 'w-full max-w-[1180px] px-[clamp(20px,4vw,56px)] py-10')

@section('content')
    @php
        // Figma's .dash-grid: as many 300px+ columns as fit, 18px apart.
        $gridClasses = 'mt-4 grid grid-cols-[repeat(auto-fill,minmax(300px,1fr))] gap-4.5';
    @endphp

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-studio-muted">Översikt</p>
            <h1 class="mt-1 text-[34px] font-semibold tracking-tight">Dina magasin</h1>
            <p class="mt-2 text-studio-muted">Här ser du magasinen du har tillgång till, både de som är publicerade och de som byggs.</p>
        </div>
        <div class="flex items-center gap-4 text-sm font-medium">
            @can('manage-staff')
                <a href="{{ route('admin.staff.index') }}" class="underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Personal</a>
            @endcan
            @can('viewAny', App\Models\Template::class)
                <a href="{{ route('admin.templates.index') }}" class="underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Mallar</a>
            @endcan
        </div>
    </div>

    <section class="mt-10" aria-labelledby="published-heading">
        <h2 id="published-heading" class="text-lg font-semibold">
            Publicerade sajter <span class="font-normal text-studio-muted">({{ $published->total() }})</span>
        </h2>
        @if ($published->isEmpty())
            <p class="mt-4 text-sm text-studio-muted">Inga publicerade sajter ännu. Publicera ett utkast så hamnar det här.</p>
        @else
            <ul class="{{ $gridClasses }}">
                @foreach ($published as $superblog)
                    <li><x-admin.superblog-card :superblog="$superblog" /></li>
                @endforeach
            </ul>
        @endif
        @if ($published->hasPages())
            <div class="mt-6">{{ $published->links() }}</div>
        @endif
    </section>

    <section class="mt-12" aria-labelledby="drafts-heading">
        <h2 id="drafts-heading" class="text-lg font-semibold">
            Under uppbyggnad <span class="font-normal text-studio-muted">({{ $drafts->total() }})</span>
        </h2>
        @if ($drafts->isEmpty())
            <p class="mt-4 text-sm text-studio-muted">Inga magasin under uppbyggnad. Starta ett med “+ Nytt magasin” i menyn till vänster.</p>
        @else
            <ul class="{{ $gridClasses }}">
                @foreach ($drafts as $superblog)
                    <li><x-admin.superblog-card :superblog="$superblog" /></li>
                @endforeach
            </ul>
        @endif
        @if ($drafts->hasPages())
            <div class="mt-6">{{ $drafts->links() }}</div>
        @endif
    </section>

    @can('create', App\Models\Superblog::class)
        @include('admin.superblogs._create-modal')
    @endcan
@endsection
