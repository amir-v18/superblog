@php
    // The hidden _form field tells this modal apart from other forms, so only its own errors reopen it.
    $reopen = $errors->any() && old('_form') === 'create-superblog';
    // With a single active template there is nothing to choose, so it starts selected.
    $selectedTemplate = old('template_id', $templates->count() === 1 ? $templates->first()->id : null);
@endphp

<dialog id="create-superblog" aria-labelledby="create-superblog-heading" @if ($reopen) data-open-on-load @endif
        class="m-auto w-full max-w-lg rounded-xl bg-studio-panel p-0 text-studio-ink shadow-xl backdrop:bg-black/60">
    {{-- Clicks on this inner wrapper never reach the dialog itself, which is how app.js detects clicks outside. --}}
    <div class="p-6">
        <h2 id="create-superblog-heading" class="text-xl font-semibold tracking-tight">Nytt magasin</h2>
        <p class="mt-1 text-sm text-studio-muted">Ge magasinet ett namn, välj mall och lägg till en webbadress. Allt kan ändras senare.</p>

        @if ($reopen)
            <div role="alert" class="mt-4 rounded-lg border border-studio-bad/40 bg-[#43261c] p-4 text-sm text-studio-bad">
                <p class="font-medium">Rätta följande:</p>
                <ul class="mt-2 list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($templates->isEmpty())
            <p role="status" class="mt-4 rounded-lg border border-[#4a4430] bg-[#2c2a24] p-4 text-sm text-[#e2d6a8]">
                Det finns ingen aktiv mall. En mall måste finnas innan du kan skapa ett magasin.
                @can('viewAny', App\Models\Template::class)
                    <a href="{{ route('admin.templates.index') }}" class="font-medium underline underline-offset-4">Hantera mallar</a>
                @endcan
            </p>
        @endif

        <form method="POST" action="{{ route('admin.superblogs.store') }}" class="mt-6 space-y-6">
            @csrf
            <input type="hidden" name="_form" value="create-superblog">

            <div>
                <label for="create-name" class="block text-sm font-medium"><span class="text-studio-muted">1</span> Namn</label>
                <input id="create-name" name="name" value="{{ old('name') }}" required maxlength="255" autofocus data-domain-source
                       class="mt-2 w-full rounded-lg border border-studio-line bg-studio-bg p-3 focus-visible:outline-2 focus-visible:outline-studio-brand">
            </div>

            <fieldset>
                <legend class="text-sm font-medium"><span class="text-studio-muted">2</span> Mall</legend>
                @include('admin.superblogs._template-options')
            </fieldset>

            <div>
                <label for="create-domain" class="block text-sm font-medium"><span class="text-studio-muted">3</span> Webbadress (valfri)</label>
                {{-- The prefix is only visual: the backend expects a bare hostname. --}}
                <div class="mt-2 flex rounded-lg border border-studio-line bg-studio-bg focus-within:outline-2 focus-within:outline-studio-brand">
                    <span aria-hidden="true" class="flex items-center rounded-l-lg bg-studio-pill px-3 text-sm text-studio-muted">https://</span>
                    <input id="create-domain" name="domain" value="{{ old('domain') }}" maxlength="253" placeholder="dagensbudbarare.se" aria-describedby="create-domain-help" data-domain-target
                           class="w-full min-w-0 rounded-r-lg bg-transparent p-3 placeholder:text-[#70727c] focus:outline-none">
                </div>
                <p id="create-domain-help" class="mt-1 text-sm text-studio-muted">Föreslås utifrån namnet. Skriv bara domänen, utan https://.</p>
            </div>

            <div class="flex justify-end gap-3">
                {{-- type="button", not a dialog submit: Enter in a field triggers the first submit button, which must be "Skapa". --}}
                <button type="button" data-close-dialog class="rounded-lg border border-studio-line bg-studio-panel px-4 py-2 text-sm font-medium hover:border-[#5a5d69] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand">Avbryt</button>
                <button type="submit" @disabled($templates->isEmpty()) class="rounded-lg bg-studio-brand px-4 py-2 text-sm font-medium text-studio-side hover:bg-[#ff9068] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-studio-brand disabled:cursor-not-allowed disabled:opacity-50">Skapa och öppna</button>
            </div>
        </form>
    </div>
</dialog>
