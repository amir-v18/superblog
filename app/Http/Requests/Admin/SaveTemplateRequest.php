<?php

namespace App\Http\Requests\Admin;

use App\Models\Template;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $template = $this->route('template');

        return $template instanceof Template && $this->user()->can('update', $template);
    }

    public function rules(): array
    {
        $template = $this->route('template');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('templates', 'name')->ignore($template)],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
