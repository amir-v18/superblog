{{-- Expects $templates and $selectedTemplate (a template id or null). --}}
<div class="mt-2 grid gap-3 sm:grid-cols-2">
    @foreach ($templates as $template)
        <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-300 p-3 text-sm has-checked:border-slate-900 has-checked:ring-1 has-checked:ring-slate-900">
            <input type="radio" name="template_id" value="{{ $template->id }}" required @checked((string) $selectedTemplate === (string) $template->id)>
            <span class="font-medium">{{ $template->name }}{{ $template->is_active ? '' : ' (inaktiv)' }}</span>
        </label>
    @endforeach
</div>
