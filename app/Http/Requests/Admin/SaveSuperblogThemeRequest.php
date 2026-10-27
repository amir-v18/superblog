<?php

namespace App\Http\Requests\Admin;

use App\Support\Theme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSuperblogThemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('superblog'));
    }

    public function rules(): array
    {
        return [
            // Only themes defined in app.css are accepted, so the stored name is always safe to output.
            'theme' => ['required', 'string', Rule::in(Theme::NAMES)],
        ];
    }
}
