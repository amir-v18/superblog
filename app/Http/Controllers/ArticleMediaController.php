<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Superblog;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArticleMediaController extends Controller
{
    public function admin(Superblog $superblog, Article $article): StreamedResponse
    {
        Gate::authorize('manageArticles', $superblog);
        $article = $superblog->articles()->whereKey($article->getKey())->with('image')->firstOrFail();

        return $this->stream($article);
    }

    public function public(Superblog $superblog, Article $article): StreamedResponse
    {
        abort_unless($superblog->status === 'published', 404);

        $article = $superblog->articles()
            ->whereKey($article->getKey())
            ->where('status', 'published')
            ->where('is_selected_for_publication', true)
            ->whereNotNull('published_at')
            ->with('image')
            ->firstOrFail();

        return $this->stream($article);
    }

    private function stream(Article $article): StreamedResponse
    {
        $media = $article->image;
        if (! $media || ! in_array($media->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            abort(404);
        }

        $path = $media->path;
        if (! is_string($path)
            || ! str_starts_with($path, 'articles/'.$article->getKey().'/')
            || preg_match('#^articles/'.preg_quote((string) $article->getKey(), '#').'/[A-Za-z0-9_-]+\.(?:jpe?g|png|webp)$#i', $path) !== 1) {
            abort(404);
        }

        $stream = Storage::disk('article_media')->readStream($path);
        if (! is_resource($stream)) {
            abort(404);
        }

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            // Visibility is rechecked for every request, including after an article is unpublished.
            'Cache-Control' => 'no-store',
        ]);
    }
}
