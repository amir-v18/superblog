@php
    // The _form field, which is not shown, tells this modal apart from the page's own forms. Old input is shared by every
    // form on the page, so errors and typed values are only used here when this modal was the one submitted.
    $reopen = $errors->any() && old('_form') === 'create-superblog';
    // $defaultTemplate (an id or null) comes from the component class, which reads it from the template registry.
    $selectedTemplate = $reopen ? old('template_id', $defaultTemplate) : $defaultTemplate;
    // One message per field, under the field. The slug is built from the name (also for the duplicate
    // check), so its error belongs under Namn.
    $nameError = $reopen ? ($errors->first('name') ?: $errors->first('slug')) : null;
    $templateError = $reopen ? $errors->first('template_id') : null;
    $domainError = $reopen ? $errors->first('domain') : null;
@endphp

<dialog id="create-superblog" aria-labelledby="create-superblog-heading" @if ($reopen) data-open-on-load @endif
        class="m-auto max-h-[92vh] w-full max-w-[760px] overflow-y-auto rounded-[14px] bg-studio-panel p-0 text-studio-ink shadow-xl backdrop:bg-black/60">
    {{-- Clicks on this inner wrapper never reach the dialog itself, which is how app.js detects clicks outside. --}}
    <div class="flex flex-col gap-4 p-6.5">
        <h2 id="create-superblog-heading" class="text-[22px] font-semibold tracking-tight">Nytt magasin</h2>
        <p class="text-[13px] leading-normal text-studio-muted">Tre uppgifter räcker för att starta. Allt kan justeras i byggaren, och mallen kan bytas senare.</p>

        @if ($templates->isEmpty())
            <p role="status" class="rounded-lg border border-[#4a4430] bg-[#2c2a24] p-4 text-sm text-[#e2d6a8]">
                Det finns ingen aktiv mall. En mall måste finnas innan du kan skapa ett magasin.
                @can('viewAny', App\Models\Template::class)
                    <a href="{{ route('admin.templates.index') }}" class="font-medium underline underline-offset-4">Hantera mallar</a>
                @endcan
            </p>
        @endif

        <form method="POST" action="{{ route('admin.superblogs.store') }}" class="flex flex-col gap-4">
            @csrf
            <input type="hidden" name="_form" value="create-superblog">

            {{-- Step headings: number, bold title and an optional muted hint. The number is decorative, so screen
                 readers hear only the title. --}}
            <div class="mt-1.5 flex flex-col gap-4">
                <label for="create-name" class="flex items-center gap-2.5">
                    <span aria-hidden="true" class="grid size-6 shrink-0 place-items-center rounded-full bg-studio-brand text-xs font-bold text-white">1</span>
                    <span class="text-sm font-bold">Namn</span>
                </label>
                <x-admin.input id="create-name" name="name" value="{{ $reopen ? old('name') : '' }}" required maxlength="255" autofocus data-domain-source
                               placeholder="t.ex. Dagens Budbärare" :aria-invalid="$nameError ? 'true' : null" :aria-describedby="$nameError ? 'create-name-error' : null" />
                @if ($nameError)
                    <p id="create-name-error" class="text-[13px] font-semibold text-studio-bad">{{ $nameError }}</p>
                @endif
            </div>

            {{-- By default a fieldset is at least as wide as its content, which would push the cards past the edge on phones. --}}
            <fieldset class="mt-1.5 min-w-0" @if ($templateError) aria-describedby="create-template-error" @endif>
                <legend class="mb-4">
                    <span class="flex items-center gap-2.5">
                        <span aria-hidden="true" class="grid size-6 shrink-0 place-items-center rounded-full bg-studio-brand text-xs font-bold text-white">2</span>
                        <span class="text-sm font-bold">Mall</span>
                        <small class="text-[12.5px] text-studio-muted">Styr layouten på hela sajten</small>
                    </span>
                </legend>
                {{-- Each card is a label around a radio that only screen readers see, so the choice is sent as template_id
                     without JS. The radio gets the keyboard focus, so the card draws the focus indicator in its place,
                     with the same values as the other admin buttons. --}}
                <div class="grid gap-3 sm:grid-cols-3">
                    @foreach ($templates as $template)
                        <label class="flex cursor-pointer flex-col overflow-hidden rounded-[10px] border-2 border-studio-line bg-studio-panel has-checked:border-studio-brand has-checked:ring-3 has-checked:ring-studio-brand/20 has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-studio-brand">
                            <input type="radio" name="template_id" value="{{ $template->id }}" required @checked((string) $selectedTemplate === (string) $template->id) class="sr-only">
                            <x-admin.theme-thumb variant="template" :theme="App\Support\Theme::forTemplate($template)" :name="$template->name" />
                            <span class="px-3 pt-2.5 pb-0.5 text-sm font-bold">{{ $template->name }}</span>
                            @if (filled($template->description))
                                <small class="px-3 pb-3 text-xs leading-[1.4] text-studio-muted">{{ $template->description }}</small>
                            @endif
                        </label>
                    @endforeach
                </div>
                @if ($templateError)
                    <p id="create-template-error" class="mt-4 text-[13px] font-semibold text-studio-bad">{{ $templateError }}</p>
                @endif
            </fieldset>

            <div class="mt-1.5">
                <x-admin.domain-input id="create-domain" :value="$reopen ? old('domain') : null" :error="$domainError" data-domain-target
                                      help="Du kopplar domänen med DNS-inställningar i sista steget innan publicering. Har du ingen domän än kan du ändra adressen senare.">
                    <span class="flex items-center gap-2.5">
                        <span aria-hidden="true" class="grid size-6 shrink-0 place-items-center rounded-full bg-studio-brand text-xs font-bold text-white">3</span>
                        <span class="font-bold">Webbadress</span>
                        <small class="text-[12.5px] font-normal text-studio-muted">Adressen där sajten publiceras (valfri)</small>
                    </span>
                </x-admin.domain-input>
            </div>

            <div class="flex justify-end gap-2.5">
                {{-- type="button", not a dialog submit: Enter in a field triggers the first submit button, which must be "Skapa". --}}
                <button type="button" data-close-dialog class="rounded-lg border border-studio-line bg-studio-panel px-4 py-2 text-sm font-medium hover:border-[#5a5d69] studio-focus-ring">Avbryt</button>
                <x-admin.button :disabled="$templates->isEmpty()" class="disabled:cursor-not-allowed disabled:opacity-50">Skapa och öppna</x-admin.button>
            </div>
        </form>
    </div>
</dialog>
