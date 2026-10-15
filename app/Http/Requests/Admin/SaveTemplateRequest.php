<?php

namespace App\Http\Requests\Admin;

use App\Models\Template;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $template = $this->route('template');

        return $template instanceof Template
            ? $this->user()->can('update', $template)
            : $this->user()->can('create', Template::class);
    }

    protected function prepareForValidation(): void
    {
        $slug = $this->input('slug');
        $name = $this->input('name');

        $this->merge([
            'slug' => is_string($slug) ? Str::slug($slug) : ($slug === null && is_string($name) ? Str::slug($name) : $slug),
        ]);
    }

    public function rules(): array
    {
        $template = $this->route('template');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('templates')->ignore($template)],
            'description' => ['bail', 'nullable', 'string', function ($attribute, $value, $fail) {
                if (strlen($value) > 65535) {
                    $fail('The description must not exceed 65,535 bytes.');
                }
            }],
            'layout' => ['required', 'string', 'max:255', Rule::in(array_keys(Template::availableLayouts()))],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
