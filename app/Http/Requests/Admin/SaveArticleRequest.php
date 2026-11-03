<?php

namespace App\Http\Requests\Admin;

use App\Models\Article;
use App\Models\Superblog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $superblog = $this->route('superblog');
        $article = $this->route('article');

        return $superblog instanceof Superblog
            && $article instanceof Article
            && (int) $article->superblog_id === (int) $superblog->getKey()
            && in_array($article->status, ['draft', 'published'], true)
            && $this->user()->can('update', $superblog);
    }

    protected function prepareForValidation(): void
    {
        $slug = $this->input('slug');

        if (is_string($slug)) {
            $this->merge(['slug' => Str::slug($slug)]);
        }
    }

    public function rules(): array
    {
        $superblog = $this->route('superblog');
        $article = $this->route('article');

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('articles', 'slug')->where('superblog_id', $superblog->getKey())->ignore($article),
            ],
            'content' => ['required', 'string'],
            'excerpt' => ['nullable', 'string', function ($attribute, $value, $fail): void {
                // The database column is TEXT, whose limit is measured in bytes.
                if (strlen($value) > 65535) {
                    $fail('The excerpt must not exceed 65,535 bytes.');
                }
            }],
        ];
    }
}
