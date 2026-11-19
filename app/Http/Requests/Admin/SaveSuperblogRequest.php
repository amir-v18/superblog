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
        $routeSuperblog = $this->route('superblog');
        $isCreating = ! ($routeSuperblog instanceof Superblog);

        if ($slug === null && $isCreating && is_string($name)) {
            $slug = Str::slug($name);
        } elseif (is_string($slug)) {
            $slug = Str::slug($slug);
        }

        $this->merge([
            'slug' => $slug,
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
            'template_id' => [
                'bail',
                $superblog instanceof Superblog ? 'sometimes' : 'required',
                'integer',
                function ($attribute, $value, $fail) use ($superblog) {
                    if ($superblog instanceof Superblog) {
                        if ((int) $superblog->template_id !== (int) $value) {
                            $fail('A Superblog template cannot be changed after creation.');
                        }

                        return;
                    }

                    $template = Template::find($value);

                    if (! $template || ! $template->is_active) {
                        $fail('Select an active template.');
                    }
                },
            ],
        ];
    }
}
