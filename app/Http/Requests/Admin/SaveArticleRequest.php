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
            'content' => ['required', 'string'],
        ];
    }
}
