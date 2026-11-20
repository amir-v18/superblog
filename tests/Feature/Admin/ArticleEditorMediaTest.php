<?php

namespace Tests\Feature\Admin;

use App\Enums\ArticleContentFormat;
use App\Models\Article;
use App\Models\Superblog;
use App\Models\Template;
use App\Models\User;
use App\Support\ArticleContentDocument;
use App\Support\LegacyArticleDocumentConverter;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ArticleEditorMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_article_can_upload_and_publicly_render_one_private_featured_image(): void
    {
        Storage::fake('article_media');
        [$admin, $superblog] = $this->makeSuperblog();

        $this->actingAs($admin)->post(route('admin.superblogs.articles.store', $superblog), [
            'title' => 'Image story',
            'slug' => 'image-story',
            'document' => $this->document('A story with an image.'),
            'image' => UploadedFile::fake()->image('field.jpg', 1200, 800),
            'image_alt_text' => 'A field at sunrise',
        ])->assertRedirect();

        $article = Article::query()->where('slug', 'image-story')->firstOrFail()->load('image');
        $this->assertSame(ArticleContentFormat::BlocksV1, $article->content_format);
        $this->assertSame('A field at sunrise', $article->image->alt_text);
        $this->assertCount(1, $article->image()->get());
        Storage::disk('article_media')->assertExists($article->image->path);
        $this->assertSame('private', config('filesystems.disks.article_media.visibility'));

        $document = (new ArticleContentDocument)->readFrom($article);
        $imageBlock = collect($document['blocks'])->firstWhere('type', 'image');
        $this->assertSame((int) $article->image->getKey(), $imageBlock['media_id']);

        $this->post(route('admin.superblogs.articles.publish', [$superblog, $article]))->assertRedirect();
        $superblog->update(['status' => 'published']);
        $imageUrl = route('public.superblogs.articles.image', [$superblog, $article]);
        $this->get($imageUrl)->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('public.superblogs.articles.show', [$superblog, $article]))
            ->assertOk()
            ->assertSee($imageUrl, false)
            ->assertSee('alt="A field at sunrise"', false);
    }

    public function test_article_image_upload_requires_alt_text_unless_marked_decorative(): void
    {
        Storage::fake('article_media');
        [$admin, $superblog] = $this->makeSuperblog();
        $base = [
            'title' => 'Accessible image',
            'slug' => 'accessible-image',
            'document' => $this->document('Introduction.'),
            'image' => UploadedFile::fake()->image('cover.png'),
        ];

        $this->actingAs($admin)->from(route('admin.superblogs.articles.create', $superblog))
            ->post(route('admin.superblogs.articles.store', $superblog), $base)
            ->assertSessionHasErrors('image_alt_text');
        $this->get(route('admin.superblogs.articles.create', $superblog))
            ->assertOk()
            ->assertSee('Introduction.');
        $this->assertDatabaseCount('articles', 0);

        $this->post(route('admin.superblogs.articles.store', $superblog), [...$base, 'image_decorative' => '1'])
            ->assertSessionHasNoErrors();
        $article = Article::query()->where('slug', 'accessible-image')->firstOrFail()->load('image');
        $this->assertSame('', $article->image->alt_text);

        $this->post(route('admin.superblogs.articles.publish', [$superblog, $article]))->assertRedirect();
        $superblog->update(['status' => 'published']);
        $this->get(route('public.superblogs.articles.show', [$superblog, $article]))
            ->assertOk()
            ->assertSee('<img', false)
            ->assertSee('alt=""', false);
    }

    public function test_manual_article_shows_brand_link_in_sources_and_product_link_in_its_paragraph(): void
    {
        [$admin, $superblog] = $this->makeSuperblog();
        $article = $this->makeArticle($superblog, 'Product article', [
            'status' => 'published',
            'is_selected_for_publication' => true,
            'published_at' => now(),
        ]);
        $productUrl = 'https://customer.example.test/products/example';
        $document = [
            'version' => 1,
            'blocks' => [[
                'id' => (string) Str::uuid(),
                'type' => 'introduction',
                'runs' => [
                    ['text' => 'Read about '],
                    ['text' => 'the Example product', 'href' => $productUrl],
                    ['text' => ' and its details.'],
                ],
            ]],
        ];
        (new ArticleContentDocument)->storeOn($article, $document);
        $article->save();
        $article->articleLinks()->createMany([
            ['type' => 'brand', 'url' => 'https://customer.example.test', 'anchor_text' => 'Example brand'],
            ['type' => 'product', 'url' => $productUrl, 'anchor_text' => 'Example product'],
        ]);
        $superblog->update(['status' => 'published']);

        $this->get(route('public.superblogs.articles.show', [$superblog, $article]))
            ->assertOk()
            ->assertSee('Källor')
            ->assertSee('Varumärkets webbplats:')
            ->assertSee('Example brand')
            ->assertSee('<a href="'.$productUrl.'" rel="sponsored">the Example product</a>', false)
            ->assertDontSee('Produkt eller tjänst:')
            ->assertDontSee('Källor och länkar')
            ->assertDontSee('Läs mer:');
    }

    public function test_upload_rejects_non_raster_image_files(): void
    {
        Storage::fake('article_media');
        [$admin, $superblog] = $this->makeSuperblog();

        $this->actingAs($admin)->from(route('admin.superblogs.articles.create', $superblog))
            ->post(route('admin.superblogs.articles.store', $superblog), [
                'title' => 'Unsafe image',
                'slug' => 'unsafe-image',
                'document' => $this->document('Introduction.'),
                'image' => UploadedFile::fake()->create('unsafe.svg', 1, 'image/svg+xml'),
                'image_alt_text' => 'An unsafe image',
            ])
            ->assertSessionHasErrors('image');

        $this->assertDatabaseCount('articles', 0);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_replacing_and_removing_an_image_updates_only_its_private_file_and_media_record(): void
    {
        Storage::fake('article_media');
        [$admin, $superblog] = $this->makeSuperblog();
        $article = $this->makeArticle($superblog, 'Replace image');
        $oldPath = 'articles/'.$article->getKey().'/old.jpg';
        Storage::disk('article_media')->put($oldPath, 'old image bytes');
        $media = $article->image()->create([
            'path' => $oldPath,
            'filename' => 'old.jpg',
            'mime_type' => 'image/jpeg',
            'alt_text' => 'Old description',
        ]);
        (new ArticleContentDocument)->storeOn($article, $this->documentArray('Introduction.', [
            ['id' => (string) Str::uuid(), 'type' => 'image', 'media_id' => $media->getKey(), 'caption' => 'A caption'],
        ]));
        $article->save();

        $replaceDocument = $this->documentArray('Introduction.', [
            ['id' => (string) Str::uuid(), 'type' => 'image', 'media_id' => $media->getKey(), 'caption' => 'Updated caption'],
        ]);
        $this->actingAs($admin)->put(route('admin.superblogs.articles.update', [$superblog, $article]), [
            'title' => $article->title,
            'slug' => $article->slug,
            'document' => json_encode($replaceDocument, JSON_THROW_ON_ERROR),
            'image' => UploadedFile::fake()->image('missing-alt.webp'),
        ])->assertSessionHasErrors('image_alt_text');
        $this->assertSame('Old description', $article->fresh()->image->alt_text);

        $this->actingAs($admin)->put(route('admin.superblogs.articles.update', [$superblog, $article]), [
            'title' => $article->title,
            'slug' => $article->slug,
            'document' => json_encode($replaceDocument, JSON_THROW_ON_ERROR),
            'image' => UploadedFile::fake()->image('replacement.webp'),
            'image_alt_text' => 'Replacement description',
        ])->assertRedirect();

        $article->refresh()->load('image');
        $replacementPath = $article->image->path;
        $this->assertNotSame($oldPath, $replacementPath);
        $this->assertSame('Replacement description', $article->image->alt_text);
        $this->assertSame('image/webp', $article->image->mime_type);
        Storage::disk('article_media')->assertMissing($oldPath);
        Storage::disk('article_media')->assertExists($replacementPath);
        $this->assertDatabaseCount('media', 1);

        $this->put(route('admin.superblogs.articles.update', [$superblog, $article]), [
            'title' => $article->title,
            'slug' => $article->slug,
            'document' => $this->document('Introduction.'),
            'remove_image' => '1',
        ])->assertRedirect();

        $this->assertDatabaseMissing('media', ['id' => $media->getKey()]);
        Storage::disk('article_media')->assertMissing($replacementPath);
        $this->assertSame([], collect((new ArticleContentDocument)->readFrom($article->fresh())['blocks'])->where('type', 'image')->all());
    }

    public function test_only_the_article_owner_can_reference_media_and_admin_image_route_is_authorized(): void
    {
        Storage::fake('article_media');
        [$admin, $superblog] = $this->makeSuperblog();
        $article = $this->makeArticle($superblog, 'Target article');
        $foreignMediaArticle = $this->makeArticle($superblog, 'Other article');
        $otherMedia = $foreignMediaArticle->image()->create([
            'path' => 'articles/'.$foreignMediaArticle->getKey().'/image.jpg',
            'filename' => 'image.jpg',
            'mime_type' => 'image/jpeg',
            'alt_text' => 'Other article image',
        ]);
        (new ArticleContentDocument)->storeOn($article, $this->documentArray('Keep this content.', [
            ['id' => (string) Str::uuid(), 'type' => 'image', 'media_id' => $otherMedia->getKey(), 'caption' => 'Foreign image'],
        ]));
        $article->save();

        $this->actingAs($admin)->put(route('admin.superblogs.articles.update', [$superblog, $article]), [
            'title' => $article->title,
            'slug' => $article->slug,
            'document' => $article->content,
        ])->assertSessionHasErrors('document');
        $this->assertSame(ArticleContentFormat::BlocksV1, $article->fresh()->content_format);

        $unassigned = $this->makeUser();
        $this->actingAs($unassigned)
            ->get(route('admin.superblogs.articles.image', [$superblog, $foreignMediaArticle]))
            ->assertForbidden();
        $this->assertDatabaseHas('media', ['id' => $otherMedia->getKey(), 'article_id' => $foreignMediaArticle->getKey()]);
    }

    public function test_public_image_route_rejects_drafts_and_unpublished_superblogs(): void
    {
        Storage::fake('article_media');
        [$admin, $superblog] = $this->makeSuperblog();
        $article = $this->makeArticle($superblog, 'Private image');
        $media = $article->image()->create([
            'path' => 'articles/'.$article->getKey().'/private.jpg',
            'filename' => 'private.jpg',
            'mime_type' => 'image/jpeg',
            'alt_text' => 'Private image',
        ]);
        Storage::disk('article_media')->put($media->path, 'bytes');
        $url = route('public.superblogs.articles.image', [$superblog, $article]);

        $this->get($url)->assertNotFound();
        $article->update(['status' => 'published', 'published_at' => now(), 'is_selected_for_publication' => true]);
        $this->get($url)->assertNotFound();
        $superblog->update(['status' => 'published']);
        $this->get($url)->assertOk();
    }

    public function test_legacy_article_with_supported_html_can_be_converted_without_changing_rss_snapshot_or_publication_fields(): void
    {
        [$admin, $superblog] = $this->makeSuperblog();
        $source = $superblog->rssSources()->create(['name' => 'Feed', 'url' => 'https://feed.example.test/rss']);
        $item = $source->rssItems()->create([
            'title' => 'Original feed title',
            'url' => 'https://feed.example.test/item',
            'content' => 'Immutable original RSS snapshot.',
        ]);
        $article = $this->makeArticle($superblog, 'Legacy edited article', [
            'source_type' => 'rss',
            'rss_item_id' => $item->getKey(),
            'content' => '<p>Intro with <strong>bold</strong>, <em>italic</em> and <a href="https://example.test/read">a link</a>.</p><h2>Main section</h2><ul><li>One</li><li>Two</li></ul>',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'is_selected_for_publication' => true,
        ]);
        $publishedAt = $article->published_at->copy();
        $modifiedAt = now()->subHours(2)->startOfSecond();
        $article->forceFill(['content_modified_at' => $modifiedAt])->save();
        $original = $item->content;
        $converter = app(LegacyArticleDocumentConverter::class);
        $document = $converter->convert($article->content);
        $this->assertNotNull($document);

        $this->actingAs($admin)->put(route('admin.superblogs.articles.update', [$superblog, $article]), [
            'title' => $article->title,
            'slug' => $article->slug,
            'document' => json_encode($document, JSON_THROW_ON_ERROR),
        ])->assertRedirect();

        $article->refresh();
        $this->assertSame(ArticleContentFormat::BlocksV1, $article->content_format);
        $this->assertSame('published', $article->status);
        $this->assertTrue($publishedAt->equalTo($article->published_at));
        $this->assertTrue($modifiedAt->equalTo($article->content_modified_at));
        $this->assertSame($original, $item->fresh()->content);
        $converted = (new ArticleContentDocument)->readFrom($article);
        $this->assertSame(['bold'], $converted['blocks'][0]['runs'][1]['marks']);
        $linkedRun = collect($converted['blocks'][0]['runs'])->first(fn (array $run): bool => isset($run['href']));
        $this->assertSame('https://example.test/read', $linkedRun['href']);
        $this->assertSame('heading', $converted['blocks'][1]['type']);
        $this->assertSame('bullet', $converted['blocks'][2]['style']);

        $superblog->update(['status' => 'published']);
        $this->get(route('public.superblogs.articles.show', [$superblog, $article]))
            ->assertOk()
            ->assertSee('<strong>bold</strong>', false)
            ->assertSee('<em>italic</em>', false)
            ->assertSee('<a href="https://example.test/read">a link</a>', false)
            ->assertSee('<h2>Main section</h2>', false)
            ->assertSee('<ul><li>One</li><li>Two</li></ul>', false);
    }

    public function test_database_enforces_one_media_record_per_article(): void
    {
        [$admin, $superblog] = $this->makeSuperblog();
        $article = $this->makeArticle($superblog, 'Unique media');
        $attributes = ['path' => 'articles/1/image.jpg', 'filename' => 'image.jpg', 'mime_type' => 'image/jpeg', 'alt_text' => 'Description'];
        $article->image()->create($attributes);

        $this->expectException(QueryException::class);
        $article->image()->create([...$attributes, 'path' => 'articles/1/second.jpg']);
    }

    public function test_legacy_body_h1_is_preserved_as_paragraph_text_during_safe_conversion(): void
    {
        $converter = app(LegacyArticleDocumentConverter::class);
        $document = $converter->convert('<h1>Legacy heading text</h1><p>Next paragraph.</p>');

        $this->assertNotNull($document);
        $this->assertSame('introduction', $document['blocks'][0]['type']);
        $this->assertSame('Legacy heading text', $document['blocks'][0]['runs'][0]['text']);
        $this->assertSame('paragraph', $document['blocks'][1]['type']);
        $this->assertSame('Next paragraph.', $document['blocks'][1]['runs'][0]['text']);
    }

    private function makeSuperblog(): array
    {
        $admin = $this->makeUser(true);
        $superblog = $this->makeBlog($admin, 'Editor Superblog');

        return [$admin, $superblog];
    }

    private function makeBlog(User $owner, string $name): Superblog
    {
        $template = Template::create([
            'name' => 'Editor template '.$name,
            'slug' => Str::slug($name).'-editor',
            'layout' => 'layouts.public',
            'is_active' => true,
        ]);

        return $owner->superblogs()->create([
            'template_id' => $template->getKey(),
            'name' => $name,
            'slug' => Str::slug($name),
        ]);
    }

    private function makeArticle(Superblog $superblog, string $title, array $attributes = []): Article
    {
        return $superblog->articles()->create(array_merge([
            'title' => $title,
            'slug' => Str::slug($title),
            'content' => 'Original legacy body.',
            'source_type' => 'manual',
            'status' => 'draft',
            'is_selected_for_publication' => false,
        ], $attributes));
    }

    private function makeUser(bool $admin = false): User
    {
        $user = User::factory()->create();
        $user->approval_status = 'approved';
        $user->is_super_admin = $admin;
        $user->save();

        return $user;
    }

    private function document(string $introduction): string
    {
        return json_encode($this->documentArray($introduction), JSON_THROW_ON_ERROR);
    }

    private function documentArray(string $introduction, array $extraBlocks = []): array
    {
        return [
            'version' => 1,
            'blocks' => [[
                'id' => (string) Str::uuid(),
                'type' => 'introduction',
                'runs' => [['text' => $introduction]],
            ], ...$extraBlocks],
        ];
    }
}
