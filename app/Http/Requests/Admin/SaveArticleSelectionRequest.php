<?php

namespace App\Http\Requests\Admin;

use App\Models\Superblog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveArticleSelectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $superblog = $this->route('superblog');

        return $superblog instanceof Superblog
            && $this->user()->can('update', $superblog);
    }

    public function rules(): array
    {
        $superblog = $this->route('superblog');
        $belongsToSuperblog = Rule::exists('articles', 'id')
            ->where('superblog_id', $superblog->getKey());

        return [
            'visible_article_ids' => ['required', 'array', 'min:1'],
            'visible_article_ids.*' => ['required', 'integer', 'distinct', $belongsToSuperblog],
            'selected_article_ids' => ['sometimes', 'array'],
            'selected_article_ids.*' => [
                'required',
                'integer',
                'distinct',
                $belongsToSuperblog,
                Rule::in($this->input('visible_article_ids', [])),
            ],
        ];
    }
}
