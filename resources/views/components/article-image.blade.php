@props(['article', 'superblog', 'isPreview' => false, 'class' => ''])

@if ($article?->image)
    <img
        src="{{ route($isPreview ? 'admin.superblogs.articles.image' : 'public.superblogs.articles.image', [$superblog, $article]) }}"
        alt="{{ $article->image->alt_text ?? '' }}"
        class="{{ $class }}"
        loading="lazy"
        decoding="async"
    >
@endif
