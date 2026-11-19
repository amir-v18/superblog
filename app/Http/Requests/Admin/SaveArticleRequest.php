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

        if (! ($superblog instanceof Superblog) || ! $this->user()->can('manageArticles', $superblog)) {
            return false;
        }

        if ($article === null) {
            return $this->routeIs('admin.superblogs.articles.store');
        }

        return $article instanceof Article
            && (int) $article->superblog_id === (int) $superblog->getKey()
            && in_array($article->status, ['draft', 'published'], true);
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
        $uniqueSlug = Rule::unique('articles', 'slug')->where('superblog_id', $superblog->getKey());

        if ($article instanceof Article) {
            $uniqueSlug->ignore($article);
        }

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $uniqueSlug,
            ],
            'content' => [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_string($value) && strlen($value) > 2_097_152) {
                        $fail('Article content may not exceed 2 MB.');
                    }
                },
            ],
            'links' => ['sometimes', 'array:brand,product'],
            'links.brand' => ['sometimes', 'array:url,anchor_text'],
            'links.brand.url' => ['nullable', 'required_with:links.brand.anchor_text', 'url:http,https', 'max:2048'],
            'links.brand.anchor_text' => ['nullable', 'required_with:links.brand.url', 'string', 'max:255'],
            'links.product' => ['sometimes', 'array:url,anchor_text'],
            'links.product.url' => ['nullable', 'required_with:links.product.anchor_text', 'url:http,https', 'max:2048'],
            'links.product.anchor_text' => ['nullable', 'required_with:links.product.url', 'string', 'max:255'],
        ];
    }
}
