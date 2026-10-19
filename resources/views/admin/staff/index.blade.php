@extends('layouts.admin')

@section('title', 'Staff Management')

@section('content')
    <a href="{{ route('admin.dashboard') }}" class="text-sm underline underline-offset-4">Back to dashboard</a>
    <h1 class="mt-4 text-3xl font-semibold tracking-tight">Staff Management</h1>
    <p class="mt-2 text-slate-600">Review staff accounts and manage their approval status.</p>

    @if ($errors->any())
        <div role="alert" class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4 text-red-900">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <section class="mt-8 rounded-xl border border-slate-200 bg-white p-6" aria-labelledby="staff-heading">
        <h2 id="staff-heading" class="text-lg font-semibold">Staff accounts</h2>
        <ul class="mt-4 divide-y divide-slate-200">
            @forelse ($staff as $member)
                <li class="flex flex-wrap items-center justify-between gap-4 py-4">
                    <div class="min-w-0">
                        <h3 class="font-medium">{{ $member->name }} @if (auth()->user()->is($member)) <span class="text-sm text-slate-500">(you)</span> @endif</h3>
                        <p class="break-all text-sm text-slate-600">{{ $member->email }}</p>
                        <p class="mt-1 text-sm font-medium {{ $member->approval_status === 'pending' ? 'text-amber-700' : ($member->approval_status === 'approved' ? 'text-emerald-700' : 'text-red-700') }}">{{ ucfirst($member->approval_status) }}</p>
                    </div>
                    @unless (auth()->user()->is($member))
                        <div class="flex flex-wrap gap-3">
                            @if ($member->approval_status !== 'approved')
                                <form method="POST" action="{{ route('admin.staff.update', $member) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-800">Approve</button>
                                </form>
                            @endif
                            @if ($member->approval_status !== 'rejected')
                                <form method="POST" action="{{ route('admin.staff.update', $member) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="rounded-lg border border-red-300 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50">Reject</button>
                                </form>
                            @endif
                        </div>
                    @endunless
                </li>
            @empty
                <li class="py-4 text-sm text-slate-600">No staff accounts found.</li>
            @endforelse
        </ul>
        <div class="mt-4">{{ $staff->links() }}</div>
    </section>
@endsection
