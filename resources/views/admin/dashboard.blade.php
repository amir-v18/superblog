@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    <h1 class="text-3xl font-semibold tracking-tight">Dashboard</h1>
    <p class="mt-2 text-slate-600">Manage the Superblogs you have access to.</p>

    <section class="mt-8 rounded-xl border border-slate-200 bg-white p-6" aria-labelledby="management-heading">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 id="management-heading" class="text-lg font-semibold">Superblogs</h2>
            <div class="flex items-center gap-4">
                @can('manage-staff')
                    <a href="{{ route('admin.staff.index') }}" class="text-sm font-medium underline underline-offset-4">Staff Management</a>
                @endcan
                @can('create', App\Models\Superblog::class)
                    <a href="{{ route('admin.superblogs.create') }}" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Create Superblog</a>
                @endcan
            </div>
        </div>
        <ul class="mt-4 divide-y divide-slate-200">
            @forelse ($superblogs as $superblog)
                <li class="flex flex-wrap items-center justify-between gap-4 py-4">
                    <div>
                        <h3 class="font-medium">{{ $superblog->name }}</h3>
                        <p class="mt-1 break-all text-sm text-slate-600">{{ $superblog->domain ?: $superblog->slug }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ $superblog->template?->name ?? 'Template unavailable' }} · {{ $superblog->status }}</p>
                    </div>
                    <div class="flex gap-4 text-sm font-medium">
                        @can('update', $superblog)
                            <a href="{{ route('admin.superblogs.edit', $superblog) }}" class="underline underline-offset-4" aria-label="Edit {{ $superblog->name }}">Edit</a>
                        @endcan
                        @can('delete', $superblog)
                            <a href="{{ route('admin.superblogs.delete', $superblog) }}" class="text-red-700 underline underline-offset-4" aria-label="Delete {{ $superblog->name }}">Delete</a>
                        @endcan
                    </div>
                </li>
            @empty
                <li class="py-4 text-sm text-slate-600">No Superblogs are available to your account.</li>
            @endforelse
        </ul>
        <div class="mt-4">{{ $superblogs->links() }}</div>
    </section>
@endsection
