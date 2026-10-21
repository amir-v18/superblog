<?php

namespace App\Http\Requests\Admin;

use App\Models\RssSource;
use App\Models\Superblog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRssSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $superblog = $this->route('superblog');
        $rssSource = $this->route('rssSource');
        $user = $this->user();

        return $superblog instanceof Superblog
            && $user !== null
            && $user->can('update', $superblog)
            && (! $rssSource instanceof RssSource
                || (int) $rssSource->superblog_id === (int) $superblog->getKey());
    }

    public function rules(): array
    {
        $superblog = $this->route('superblog');
        $rssSource = $this->route('rssSource');
        $uniqueUrl = Rule::unique('rss_sources', 'url')
            ->where(fn ($query) => $query->where('superblog_id', $superblog->getKey()));

        if ($rssSource instanceof RssSource) {
            $uniqueUrl->ignore($rssSource);
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => [
                'bail',
                'required',
                'string',
                'url:http,https',
                $uniqueUrl,
                function ($attribute, $value, $fail) {
                    if (strlen($value) > 65535) {
                        $fail('The feed URL must not exceed 65,535 bytes.');
                    }
                },
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
