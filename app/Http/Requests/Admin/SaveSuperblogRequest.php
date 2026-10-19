<?php

namespace App\Http\Requests\Admin;

use App\Models\Superblog;
use App\Models\Template;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveSuperblogRequest extends FormRequest
{
    public function authorize(): bool
    {
        $superblog = $this->route('superblog');

        return $superblog instanceof Superblog
            ? $this->user()->can('update', $superblog)
            : $this->user()->can('create', Superblog::class);
    }

    protected function prepareForValidation(): void
    {
        $slug = $this->input('slug');
        $name = $this->input('name');
        $domain = $this->input('domain');

        $this->merge([
            'slug' => is_string($slug) ? Str::slug($slug) : ($slug === null && is_string($name) ? Str::slug($name) : $slug),
            'domain' => is_string($domain) ? strtolower(rtrim(trim($domain), '.')) : $domain,
        ]);
    }

    public function rules(): array
    {
        $superblog = $this->route('superblog');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('superblogs')->ignore($superblog)],
            'domain' => ['nullable', 'string', 'max:253', 'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?$/', Rule::unique('superblogs')->ignore($superblog)],
            'description' => ['bail', 'nullable', 'string', function ($attribute, $value, $fail) {
                // MySQL TEXT is limited by bytes, not the number of Unicode characters.
                if (strlen($value) > 65535) {
                    $fail('The description must not exceed 65,535 bytes.');
                }
            }],
            'template_id' => ['bail', 'required', 'integer', function ($attribute, $value, $fail) use ($superblog) {
                $template = Template::find($value);

                $isCurrentTemplate = $superblog instanceof Superblog
                    && (int) $superblog->template_id === (int) $value;

                if (! $template || (! $template->is_active && ! $isCurrentTemplate)) {
                    $fail('Select an active template.');
                }
            }],
        ];
    }
}
