@extends('layouts.admin')

@section('title', $superblog->name)

@section('content')
    @php
        // Tabs are plain links with ?flik=..., so only the active tab is rendered and no JS is needed.
        $tab = request()->query('flik') === 'utseende' ? 'utseende' : 'grund';
        $tabClasses = 'inline-block border-b-2 border-transparent px-1 pb-3 text-sm font-medium text-slate-600 hover:text-slate-900 aria-[current=page]:border-slate-900 aria-[current=page]:text-slate-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900';
        $selectedTemplate = old('template_id', $superblog->template_id);
    @endphp

    <a href="{{ route('admin.dashboard') }}" class="text-sm underline underline-offset-4"><span aria-hidden="true">←</span> Alla magasin</a>

    <div class="mt-4 flex flex-wrap items-center gap-3">
        <h1 class="text-3xl font-semibold tracking-tight">{{ $superblog->name }}</h1>
        @if ($superblog->status === 'published')
            <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-800"><span aria-hidden="true">●</span> Live</span>
        @else
            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700">Utkast</span>
        @endif
    </div>

    <nav aria-label="Byggarens steg" class="mt-8 border-b border-slate-200">
        <ul class="flex flex-wrap gap-6">
            <li>
                <a href="{{ route('admin.superblogs.edit', [$superblog, 'flik' => 'grund']) }}" @if ($tab === 'grund') aria-current="page" @endif class="{{ $tabClasses }}">1 Grund</a>
            </li>
            <li>
                <a href="{{ route('admin.superblogs.edit', [$superblog, 'flik' => 'utseende']) }}" @if ($tab === 'utseende') aria-current="page" @endif class="{{ $tabClasses }}">2 Utseende</a>
            </li>
            <li>
                {{-- Leaves the builder for the existing RSS pages until feeds are built into it. --}}
                <a href="{{ route('admin.superblogs.rss-sources.index', $superblog) }}" class="{{ $tabClasses }}">3 Flöden <span aria-hidden="true">↗</span></a>
            </li>
        </ul>
    </nav>

    @if ($tab === 'grund')
        @if ($errors->any())
            <div role="alert" class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4 text-red-900">
                <p class="font-medium">Rätta följande:</p>
                <ul class="mt-2 list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.superblogs.update', $superblog) }}" class="mt-6 space-y-6 rounded-xl border border-slate-200 bg-white p-6">
            @csrf
            @method('PUT')
            {{-- Without a slug, SaveSuperblogRequest regenerates it from the name, so renaming would change it. --}}
            <input type="hidden" name="slug" value="{{ old('slug', $superblog->slug) }}">

            <div>
                <label for="name" class="block text-sm font-medium">Namn</label>
                <input id="name" name="name" value="{{ old('name', $superblog->name) }}" required maxlength="255" class="mt-2 w-full rounded-lg border border-slate-300 p-3">
            </div>

            <fieldset>
                <legend class="text-sm font-medium">Mall</legend>
                @include('admin.superblogs._template-options')
            </fieldset>

            <div>
                <label for="domain" class="block text-sm font-medium">Webbadress (valfri)</label>
                {{-- The prefix is only visual: the backend expects a bare hostname. --}}
                <div class="mt-2 flex rounded-lg border border-slate-300 focus-within:outline-2 focus-within:outline-slate-900">
                    <span aria-hidden="true" class="flex items-center rounded-l-lg bg-slate-100 px-3 text-sm text-slate-500">https://</span>
                    <input id="domain" name="domain" value="{{ old('domain', $superblog->domain) }}" maxlength="253" placeholder="dagensbudbarare.se" aria-describedby="domain-help"
                           class="w-full min-w-0 rounded-r-lg p-3 focus:outline-none">
                </div>
                <p id="domain-help" class="mt-1 text-sm text-slate-600">Skriv bara domänen, utan https://.</p>
            </div>

            <div>
                <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Spara</button>
            </div>
        </form>
    @else
        <div class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-600">
            Kommer snart
        </div>
    @endif
@endsection
