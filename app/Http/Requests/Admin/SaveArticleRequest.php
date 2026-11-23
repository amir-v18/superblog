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
        $requiresDocument = $article === null || $article->content_format->value === 'blocks_v1';
        $uniqueSlug = Rule::unique('articles', 'slug')->where('superblog_id', $superblog->getKey());

        if ($article instanceof Article) {
            $uniqueSlug->ignore($article);
        }

        $hasImage = $this->hasFile('image')
            || ($article instanceof Article && $article->image()->exists() && ! $this->boolean('remove_image'));
        $requiresAltText = $hasImage && ! $this->boolean('image_decorative');

        return [
            'title' => ['required', 'string', 'max:255'],
            'author_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $uniqueSlug,
            ],
            'document' => [
                $requiresDocument ? 'required' : 'sometimes',
                'string',
                'max:2097152',
            ],
            'content' => [
                $article === null || $article->content_format->value === 'blocks_v1'
                    ? 'prohibited'
                    : 'sometimes',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_string($value) && strlen($value) > 2_097_152) {
                        $fail('Article content may not exceed 2 MB.');
                    }
                },
            ],
            'image' => ['sometimes', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'image_alt_text' => [
                $requiresAltText ? 'required' : 'nullable',
                'string',
                'max:255',
            ],
            'image_decorative' => ['sometimes', 'boolean'],
            'remove_image' => ['sometimes', 'boolean'],
            'links' => ['sometimes', 'array:brand,product'],
            'links.brand' => ['sometimes', 'array:url,anchor_text'],
            'links.brand.url' => ['nullable', 'required_with:links.brand.anchor_text', 'url:http,https', 'max:2048'],
            'links.brand.anchor_text' => ['nullable', 'required_with:links.brand.url', 'string', 'max:255'],
            'links.product' => ['sometimes', 'array:url,anchor_text'],
            'links.product.url' => ['nullable', 'required_with:links.product.anchor_text', 'url:http,https', 'max:2048'],
            'links.product.anchor_text' => ['nullable', 'required_with:links.product.url', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Ange en rubrik för artikeln.',
            'title.string' => 'Artikeltiteln måste vara text.',
            'title.max' => 'Artikeltiteln får innehålla högst 255 tecken.',
            'author_name.string' => 'Författarnamnet måste vara text.',
            'author_name.max' => 'Författarnamnet får innehålla högst 255 tecken.',
            'slug.required' => 'Ange en URL-slug för artikeln.',
            'slug.string' => 'URL-sluggen måste vara text.',
            'slug.max' => 'URL-sluggen får innehålla högst 255 tecken.',
            'slug.regex' => 'Använd små bokstäver, siffror och bindestreck i URL-sluggen.',
            'slug.unique' => 'Den här sluggen används redan av en annan artikel i Superbloggen.',
            'document.required' => 'Skriv artikelns innehåll innan du sparar.',
            'document.string' => 'Artikelns innehåll har ett ogiltigt format.',
            'document.max' => 'Artikelns innehåll får vara högst 2 MB.',
            'content.prohibited' => 'Artikelinnehållet kunde inte sparas. Ladda om sidan och försök igen.',
            'content.string' => 'Artikelns innehåll har ett ogiltigt format.',
            'image.file' => 'Välj en giltig bildfil.',
            'image.image' => 'Den valda filen måste vara en bild.',
            'image.mimes' => 'Bilden måste vara i formatet JPEG, PNG eller WebP.',
            'image.max' => 'Bilden får vara högst 5 MB.',
            'image_alt_text.required' => 'Ange en beskrivande alternativtext eller markera bilden som dekorativ.',
            'image_alt_text.string' => 'Alternativtexten måste vara text.',
            'image_alt_text.max' => 'Alternativtexten får innehålla högst 255 tecken.',
            'image_decorative.boolean' => 'Valet för dekorativ bild är ogiltigt.',
            'remove_image.boolean' => 'Valet för att ta bort bilden är ogiltigt.',
            'links.array' => 'Länkuppgifterna har ett ogiltigt format.',
            'links.brand.array' => 'Varumärkeslänken har ett ogiltigt format.',
            'links.brand.url.required_with' => 'Ange webbadressen till varumärket.',
            'links.brand.url.url' => 'Ange en giltig webbadress som börjar med http eller https.',
            'links.brand.url.max' => 'Varumärkets webbadress får innehålla högst 2 048 tecken.',
            'links.brand.anchor_text.required_with' => 'Ange länktext för varumärket.',
            'links.brand.anchor_text.string' => 'Varumärkets länktext måste vara text.',
            'links.brand.anchor_text.max' => 'Varumärkets länktext får innehålla högst 255 tecken.',
            'links.product.array' => 'Produktlänken har ett ogiltigt format.',
            'links.product.url.required_with' => 'Ange webbadressen till produkten eller tjänsten.',
            'links.product.url.url' => 'Ange en giltig webbadress som börjar med http eller https.',
            'links.product.url.max' => 'Produktens webbadress får innehålla högst 2 048 tecken.',
            'links.product.anchor_text.required_with' => 'Ange länktext för produkten eller tjänsten.',
            'links.product.anchor_text.string' => 'Produktens länktext måste vara text.',
            'links.product.anchor_text.max' => 'Produktens länktext får innehålla högst 255 tecken.',
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'artikeltitel',
            'author_name' => 'författarnamn',
            'slug' => 'URL-slug',
            'document' => 'artikelinnehåll',
            'content' => 'artikelinnehåll',
            'image' => 'artikelbild',
            'image_alt_text' => 'alternativtext',
            'image_decorative' => 'dekorativ bild',
            'remove_image' => 'ta bort bild',
        ];
    }
}
