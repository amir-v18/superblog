@extends('layouts.admin')

@section('title', 'Översikt')

@section('content')
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Översikt</p>
            <h1 class="mt-1 text-3xl font-semibold tracking-tight">Dina magasin</h1>
            <p class="mt-2 text-slate-600">Här ser du magasinen du har tillgång till, både de som är publicerade och de som byggs.</p>
        </div>
        <div class="flex items-center gap-4 text-sm font-medium">
            @can('manage-staff')
                <a href="{{ route('admin.staff.index') }}" class="underline underline-offset-4">Personal</a>
            @endcan
            @can('viewAny', App\Models\Template::class)
                <a href="{{ route('admin.templates.index') }}" class="underline underline-offset-4">Mallar</a>
            @endcan
        </div>
    </div>

    <section class="mt-10" aria-labelledby="published-heading">
        <h2 id="published-heading" class="text-lg font-semibold">
            Publicerade sajter <span class="font-normal text-slate-500">({{ $published->total() }})</span>
        </h2>
        @if ($published->isEmpty())
            <p class="mt-4 text-sm text-slate-600">Inga publicerade sajter ännu. Publicera ett utkast så hamnar det här.</p>
        @else
            <ul class="mt-4 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
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
            Under uppbyggnad <span class="font-normal text-slate-500">({{ $drafts->total() }})</span>
        </h2>
        @if ($drafts->isEmpty())
            <p class="mt-4 text-sm text-slate-600">Inga magasin under uppbyggnad. Starta ett med “+ Nytt magasin” i menyn till vänster.</p>
        @else
            <ul class="mt-4 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
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
