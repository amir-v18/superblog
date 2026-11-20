<?php

namespace Tests\Feature\Admin;

use App\Enums\ArticleContentFormat;
use App\Models\Article;
use App\Models\Superblog;
use App\Models\Template;
use App\Models\User;
use App\Support\ArticleContentDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ManualArticleCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_open_the_write_article_step_and_editor(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();

        $this->actingAs($staff)
            ->get(route('admin.superblogs.edit', [$superblog, 'flik' => 'skriv-artikel']))
            ->assertOk()
            ->assertSee('2 Skriv artikel')
            ->assertSee('3 Publicera')
            ->assertSee(route('admin.superblogs.articles.create', $superblog), false);

        $this->get(route('admin.superblogs.articles.create', $superblog))
            ->assertOk()
            ->assertSee('New manual article')
            ->assertDontSee('Excerpt')
            ->assertSee('data-article-editor', false)
            ->assertSee('data-editor-title', false)
            ->assertSee('data-editor-body', false)
            ->assertSee('data-required-marker="title"', false)
            ->assertSee('data-required-marker="slug"', false)
            ->assertSee('data-image-alt-required-marker', false)
            ->assertSee('data-required-marker="brand-url" hidden', false)
            ->assertSee('data-article-add-block="h2"', false)
            ->assertSee('data-article-add-block="h3"', false)
            ->assertSee('data-article-add-block="bullet"', false)
            ->assertSee('data-article-text-size', false)
            ->assertDontSee('data-article-add-image', false)
            ->assertSee('data-introduction-required-proxy', false)
            ->assertSee('type="text" required tabindex="-1" aria-label="Introduction paragraph"', false)
            ->assertSee('data-block-move="up" aria-label="Move block up"', false)
            ->assertSee('data-block-move="down" aria-label="Move block down"', false)
            ->assertSee('data-block-remove aria-label="Remove block"', false)
            ->assertDontSee('data-block-menu-toggle', false)
            ->assertSee('data-article-insert-link="normal"', false)
            ->assertSee('data-article-insert-link="product"', false)
            ->assertDontSee('data-article-link-dialog', false)
            ->assertSee('Save draft')
            ->assertSee('Källor')
            ->assertSee('Brand backlink')
            ->assertDontSee('Product or service backlink')
            ->assertSee('Save and publish')
            ->assertDontSee('<textarea id="content"', false)
            ->assertSee(route('admin.superblogs.articles.store', $superblog), false);
    }

    public function test_authorized_user_can_create_a_manual_draft_for_the_current_superblog(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();
        $otherSuperblog = $this->makeSuperblog($this->makeApprovedUser(superAdmin: true), 'Other Superblog');

        $response = $this->actingAs($staff)->post(route('admin.superblogs.articles.store', $superblog), [
            'title' => 'A manual story',
            'slug' => 'A Manual Story',
            'document' => $this->document('The article body.'),
            // These fields are deliberately forged: the server owns publication and source state.
            'superblog_id' => $otherSuperblog->id,
            'rss_item_id' => 99,
            'source_type' => 'rss',
            'status' => 'published',
            'is_selected_for_publication' => true,
            'published_at' => now()->toDateTimeString(),
        ]);

        $article = Article::query()->where('slug', 'a-manual-story')->firstOrFail();
        $response->assertRedirect(route('admin.superblogs.articles.edit', [$superblog, $article]))
            ->assertSessionHas('status', 'Draft article created.');

        $this->assertSame($superblog->id, $article->superblog_id);
        $this->assertSame('manual', $article->source_type);
        $this->assertSame('draft', $article->status);
        $this->assertNull($article->rss_item_id);
        $this->assertTrue($article->is_selected_for_publication);
        $this->assertNull($article->published_at);
        $this->assertSame(ArticleContentFormat::BlocksV1, $article->content_format);
        $this->assertSame('The article body.', $this->articleIntroduction($article));

        $this->get(route('admin.superblogs.edit', [$superblog, 'flik' => 'innehall']))
            ->assertOk()
            ->assertSee('A manual story')
            ->assertSee('name="selected_article_ids[]" value="'.$article->id.'"', false)
            ->assertSee('name="selected_article_ids[]" value="'.$article->id.'" checked', false);
    }

    public function test_save_and_publish_saves_a_new_manual_article_and_publishes_it(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();

        $response = $this->actingAs($staff)->post(route('admin.superblogs.articles.store', $superblog), [
            'title' => 'Ready to publish',
            'slug' => 'ready-to-publish',
            'document' => $this->document('Saved before publication.'),
            'publish_after_save' => '1',
        ]);

        $article = Article::query()->where('slug', 'ready-to-publish')->firstOrFail();
        $response->assertRedirect(route('admin.superblogs.edit', [$superblog, 'flik' => 'innehall']))
            ->assertSessionHas('status', 'Article saved and published.');
        $this->assertSame('Saved before publication.', $this->articleIntroduction($article));
        $this->assertSame('published', $article->status);
        $this->assertTrue($article->is_selected_for_publication);
        $this->assertNotNull($article->published_at);
        $this->assertNull($article->content_modified_at);
    }

    public function test_save_and_publish_saves_draft_edits_before_publishing(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();
        $this->actingAs($staff)->post(route('admin.superblogs.articles.store', $superblog), [
            'title' => 'Draft to update',
            'slug' => 'draft-to-update',
            'document' => $this->document('Original body.'),
        ])->assertRedirect();
        $article = Article::query()->where('slug', 'draft-to-update')->firstOrFail();

        $response = $this->put(route('admin.superblogs.articles.update', [$superblog, $article]), [
            'title' => 'Updated before publishing',
            'slug' => 'updated-before-publishing',
            'document' => $this->document('The saved new body.'),
            'publish_after_save' => '1',
        ]);

        $article->refresh();
        $response->assertRedirect(route('admin.superblogs.edit', [$superblog, 'flik' => 'innehall']))
            ->assertSessionHas('status', 'Article saved and published.');
        $this->assertSame('Updated before publishing', $article->title);
        $this->assertSame('The saved new body.', $this->articleIntroduction($article));
        $this->assertSame('published', $article->status);
        $this->assertNotNull($article->published_at);
        $this->assertNull($article->content_modified_at);
    }

    public function test_save_and_publish_saves_changes_to_an_already_published_article_without_resetting_its_first_date(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();
        $this->actingAs($staff)->post(route('admin.superblogs.articles.store', $superblog), [
            'title' => 'Already live',
            'slug' => 'already-live',
            'document' => $this->document('First version.'),
        ])->assertRedirect();
        $article = Article::query()->where('slug', 'already-live')->firstOrFail();
        $this->post(route('admin.superblogs.articles.publish', [$superblog, $article]));
        $publishedAt = $article->fresh()->published_at;

        $response = $this->put(route('admin.superblogs.articles.update', [$superblog, $article]), [
            'title' => 'Already live, revised',
            'slug' => 'already-live',
            'document' => $this->document('Updated version.'),
            'publish_after_save' => '1',
        ]);

        $article->refresh();
        $response->assertRedirect(route('admin.superblogs.edit', [$superblog, 'flik' => 'innehall']))
            ->assertSessionHas('status', 'Article saved and published.');
        $this->assertSame('Already live, revised', $article->title);
        $this->assertSame('Updated version.', $this->articleIntroduction($article));
        $this->assertSame('published', $article->status);
        $this->assertTrue($publishedAt->equalTo($article->published_at));
        $this->assertNotNull($article->content_modified_at);
    }

    public function test_article_document_storage_round_trips_and_preserves_publication_fields(): void
    {
        $superblog = $this->makeSuperblog($this->makeApprovedUser(superAdmin: true), 'Structured article Superblog');
        $publishedAt = now()->subDays(2)->startOfSecond();
        $modifiedAt = now()->subDay()->startOfSecond();
        $article = $this->makeManualArticle($superblog, 'Structured article', [
            'status' => 'published',
            'is_selected_for_publication' => false,
            'published_at' => $publishedAt,
        ]);
        $article->forceFill(['content_modified_at' => $modifiedAt])->save();
        $document = [
            'version' => 1,
            'blocks' => [[
                'id' => '1a912ca0-1111-4111-8111-111111111111',
                'type' => 'introduction',
                'runs' => [['text' => 'Structured introduction.']],
            ]],
        ];

        $documents = new ArticleContentDocument;
        $documents->storeOn($article, $document);
        $article->save();
        $article->refresh();

        $this->assertSame(ArticleContentFormat::BlocksV1, $article->content_format);
        $this->assertSame($documents->deserialize($documents->serialize($document)), $documents->readFrom($article));
        $this->assertSame('published', $article->status);
        $this->assertFalse($article->is_selected_for_publication);
        $this->assertTrue($publishedAt->equalTo($article->published_at));
        $this->assertTrue($modifiedAt->equalTo($article->content_modified_at));
    }

    public function test_legacy_article_content_is_unchanged_and_defaults_to_legacy_html_format(): void
    {
        $superblog = $this->makeSuperblog($this->makeApprovedUser(superAdmin: true), 'Legacy article Superblog');
        $legacyHtml = '<p>Legacy <strong>formatted</strong> text.</p><h2>Existing heading</h2>';

        $article = $this->makeManualArticle($superblog, 'Legacy article', ['content' => $legacyHtml])->fresh();

        $this->assertSame(ArticleContentFormat::LegacyHtml, $article->content_format);
        $this->assertSame($legacyHtml, $article->content);
        $this->assertNull((new ArticleContentDocument)->readFrom($article));
    }

    public function test_structured_article_text_is_rendered_as_text_not_browser_html(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();

        $this->actingAs($staff)->post(route('admin.superblogs.articles.store', $superblog), [
            'title' => 'Sanitized article',
            'slug' => 'sanitized-article',
            'document' => $this->document('<script onclick="alert(1)">literal text only</script>'),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $article = Article::query()->where('slug', 'sanitized-article')->firstOrFail();
        $this->assertSame(ArticleContentFormat::BlocksV1, $article->content_format);
        $this->assertSame('<script onclick="alert(1)">literal text only</script>', $this->articleIntroduction($article));
        $this->assertDatabaseMissing('articles', ['content' => '<script onclick="alert(1)">literal text only</script>']);
        $this->assertSame('draft', $article->status);
        $this->assertNull($article->published_at);
        $this->assertNull($article->content_modified_at);
    }

    public function test_article_body_rejects_missing_introduction_or_h3_without_an_h2(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();
        $route = route('admin.superblogs.articles.store', $superblog);

        $this->actingAs($staff)->from(route('admin.superblogs.articles.create', $superblog))
            ->post($route, [
                'title' => 'Missing introduction',
                'slug' => 'missing-introduction',
                'document' => $this->documentWithBlocks([
                    ['type' => 'heading', 'level' => 2, 'runs' => [['text' => 'Main section']]],
                    ['type' => 'paragraph', 'runs' => [['text' => 'Text after heading.']]],
                ], includeIntroduction: false),
            ])
            ->assertSessionHasErrors('document');

        $this->post($route, [
            'title' => 'Orphan subsection',
            'slug' => 'orphan-subsection',
            'document' => $this->documentWithBlocks([
                ['type' => 'heading', 'level' => 3, 'runs' => [['text' => 'Subsection first']]],
            ]),
        ])->assertSessionHasErrors('document');

        $this->assertDatabaseCount('articles', 0);
    }

    public function test_empty_introduction_error_names_the_editor_field_in_plain_language(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();
        $editorUrl = route('admin.superblogs.articles.create', $superblog);

        $this->actingAs($staff)->from($editorUrl)->followingRedirects()
            ->post(route('admin.superblogs.articles.store', $superblog), [
                'title' => 'Empty introduction',
                'slug' => 'empty-introduction',
                'document' => $this->document('   '),
            ])
            ->assertOk()
            ->assertSee('The introduction paragraph is empty. Add text to the introduction before saving.')
            ->assertDontSee('blocks.0.runs');
    }

    public function test_backend_removes_body_h1_and_keeps_only_the_title_h1_publicly(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();

        $this->actingAs($staff)->post(route('admin.superblogs.articles.store', $superblog), [
            'title' => 'The generated title',
            'slug' => 'generated-title',
            'document' => $this->documentWithBlocks([
                ['type' => 'heading', 'level' => 1, 'runs' => [['text' => 'Forged extra heading']]],
            ]),
        ])->assertSessionHasErrors('document');

        $this->actingAs($staff)->post(route('admin.superblogs.articles.store', $superblog), [
            'title' => 'The generated title',
            'slug' => 'generated-title',
            'document' => $this->documentWithBlocks([
                ['type' => 'paragraph', 'runs' => [['text' => 'Introduction paragraph.']]],
                ['type' => 'heading', 'level' => 2, 'runs' => [['text' => 'Main section']]],
                ['type' => 'paragraph', 'runs' => [['text' => 'Section body.']]],
            ]),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $article = Article::query()->where('slug', 'generated-title')->firstOrFail();
        $this->assertSame(ArticleContentFormat::BlocksV1, $article->content_format);

        $this->post(route('admin.superblogs.articles.publish', [$superblog, $article]));
        $superblog->update(['status' => 'published']);

        $response = $this->get(route('public.superblogs.articles.show', [$superblog, $article]))->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
        $response->assertSee('<h1', false)->assertSee('The generated title')->assertSee('Introduction paragraph.')
            ->assertDontSee('Forged extra heading');
    }

    public function test_article_links_are_saved_separately_and_change_public_modified_date(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();

        $this->actingAs($staff)->post(route('admin.superblogs.articles.store', $superblog), [
            'title' => 'Backlink story',
            'slug' => 'backlink-story',
            'document' => $this->documentWithBlocks([
                ['type' => 'heading', 'level' => 2, 'runs' => [['text' => 'Main section']]],
                ['type' => 'paragraph', 'runs' => [
                    ['text' => 'Body with '],
                    ['text' => 'Product name', 'href' => 'https://brand.example.test/product'],
                    ['text' => '.'],
                ]],
            ]),
            'links' => [
                'brand' => ['url' => 'https://brand.example.test', 'anchor_text' => 'Brand name'],
                'product' => ['url' => 'https://brand.example.test/product', 'anchor_text' => 'Product name'],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $article = Article::query()->where('slug', 'backlink-story')->firstOrFail();
        $this->assertDatabaseHas('article_links', [
            'article_id' => $article->id,
            'type' => 'brand',
            'url' => 'https://brand.example.test',
            'anchor_text' => 'Brand name',
        ]);
        $this->assertDatabaseHas('article_links', [
            'article_id' => $article->id,
            'type' => 'product',
            'url' => 'https://brand.example.test/product',
            'anchor_text' => 'Product name',
        ]);
        $this->actingAs($staff)
            ->get(route('admin.superblogs.articles.edit', [$superblog, $article]))
            ->assertOk()
            ->assertSee('data-editor-brand-preview', false)
            ->assertSee('href="https://brand.example.test"', false)
            ->assertSee('Brand name')
            ->assertSee('type="hidden" name="links[product][url]"', false)
            ->assertSee('type="hidden" name="links[product][anchor_text]"', false);

        $this->post(route('admin.superblogs.articles.publish', [$superblog, $article]));
        $firstPublishedAt = $article->fresh()->published_at;
        $this->travelTo(now()->addMinute());

        $this->put(route('admin.superblogs.articles.update', [$superblog, $article]), [
            'title' => 'Backlink story',
            'slug' => 'backlink-story',
            'document' => $this->documentWithBlocks([
                ['type' => 'heading', 'level' => 2, 'runs' => [['text' => 'Main section']]],
                ['type' => 'paragraph', 'runs' => [
                    ['text' => 'Body with '],
                    ['text' => 'Product name', 'href' => 'https://brand.example.test/product'],
                    ['text' => '.'],
                ]],
            ]),
            'links' => [
                'brand' => ['url' => 'https://new-brand.example.test', 'anchor_text' => 'Updated brand'],
                'product' => ['url' => 'https://brand.example.test/product', 'anchor_text' => 'Product name'],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $article->refresh();
        $this->assertSame('published', $article->status);
        $this->assertTrue($firstPublishedAt->equalTo($article->published_at));
        $this->assertNotNull($article->content_modified_at);
        $this->assertDatabaseHas('article_links', [
            'article_id' => $article->id,
            'type' => 'brand',
            'url' => 'https://new-brand.example.test',
            'anchor_text' => 'Updated brand',
        ]);

        $superblog->update(['status' => 'published']);
        $this->get(route('public.superblogs.articles.show', [$superblog, $article]))
            ->assertOk()
            ->assertSee('href="https://new-brand.example.test"', false)
            ->assertSee('Updated brand')
            ->assertSee('<a href="https://brand.example.test/product" rel="sponsored">Product name</a>', false)
            ->assertDontSee('Produkt eller tjänst:');
    }

    public function test_article_backlink_validation_rejects_unsafe_protocols_and_incomplete_pairs(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();
        $route = route('admin.superblogs.articles.store', $superblog);
        $base = [
            'title' => 'Unsafe backlink',
            'slug' => 'unsafe-backlink',
            'document' => $this->document('Introduction.'),
        ];

        $this->actingAs($staff)->from(route('admin.superblogs.articles.create', $superblog))
            ->post($route, [...$base, 'links' => ['brand' => ['url' => 'javascript:alert(1)', 'anchor_text' => 'Unsafe']]])
            ->assertSessionHasErrors('links.brand.url');

        $this->post($route, [...$base, 'links' => ['brand' => ['url' => 'https://brand.example.test', 'anchor_text' => '']]])
            ->assertSessionHasErrors('links.brand.anchor_text');

        $this->assertDatabaseCount('articles', 0);
    }

    public function test_saving_unchanged_legacy_text_does_not_create_a_false_content_modified_date(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();
        $publishedAt = now()->subDays(3)->startOfSecond();
        $article = $this->makeManualArticle($superblog, 'Legacy text article', [
            'content' => 'Existing plain body.',
            'status' => 'published',
            'published_at' => $publishedAt,
            'content_modified_at' => null,
        ]);

        $this->travelTo(now()->addMinute());
        $this->actingAs($staff)->put(route('admin.superblogs.articles.update', [$superblog, $article]), [
            'title' => 'Legacy text article',
            'slug' => 'legacy-text-article',
            'content' => 'Existing plain body.',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $article->refresh();
        $this->assertSame(ArticleContentFormat::BlocksV1, $article->content_format);
        $this->assertSame('Existing plain body.', $this->articleIntroduction($article));
        $this->assertSame('published', $article->status);
        $this->assertTrue($publishedAt->equalTo($article->published_at));
        $this->assertNull($article->content_modified_at);
    }

    public function test_manual_article_saved_from_the_form_is_shown_on_the_public_page(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();

        $this->actingAs($staff)->post(route('admin.superblogs.articles.store', $superblog), [
            'title' => 'A public manual story',
            'slug' => 'a-public-manual-story',
            'document' => $this->document('The manual body text.'),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $article = Article::query()->where('slug', 'a-public-manual-story')->firstOrFail();
        $this->post(route('admin.superblogs.articles.publish', [$superblog, $article]))
            ->assertSessionHas('status', 'Article published.');
        $superblog->update(['status' => 'published']);

        $this->get(route('public.superblogs.articles.show', [$superblog, $article]))
            ->assertOk()
            ->assertSee('A public manual story')
            ->assertSee('The manual body text.');
    }

    public function test_a_posted_excerpt_field_is_ignored_without_an_error(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();

        // Dropped twice: the form rules leave it out of the validated data, and the model would discard
        // it anyway since it is not fillable. Only if both let it through would saving fail on the
        // missing column, and this test would turn red.
        $this->actingAs($staff)->post(route('admin.superblogs.articles.store', $superblog), [
            'title' => 'An old form post',
            'slug' => 'an-old-form-post',
            'document' => $this->document('The body text.'),
            'excerpt' => 'Old ingress text',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $article = Article::query()->where('slug', 'an-old-form-post')->firstOrFail();
        $this->assertArrayNotHasKey('excerpt', $article->getAttributes());

        $this->post(route('admin.superblogs.articles.publish', [$superblog, $article]));
        $superblog->update(['status' => 'published']);

        $this->get(route('public.superblogs.articles.show', [$superblog, $article]))
            ->assertOk()
            ->assertSee('The body text.')
            ->assertDontSee('Old ingress text');
    }

    public function test_unassigned_user_cannot_create_or_edit_articles(): void
    {
        $owner = $this->makeApprovedUser(superAdmin: true);
        $superblog = $this->makeSuperblog($owner, 'Restricted Superblog');
        $article = $this->makeManualArticle($superblog, 'Private draft');
        $unassigned = $this->makeApprovedUser();

        $this->actingAs($unassigned)
            ->get(route('admin.superblogs.articles.create', $superblog))
            ->assertForbidden();

        $this->post(route('admin.superblogs.articles.store', $superblog), [
            'title' => 'Unauthorized draft',
            'slug' => 'unauthorized-draft',
            'content' => 'This must not be saved.',
        ])->assertForbidden();

        $this->get(route('admin.superblogs.articles.edit', [$superblog, $article]))
            ->assertForbidden();

        $this->put(route('admin.superblogs.articles.update', [$superblog, $article]), [
            'title' => 'Changed privately',
            'slug' => 'changed-privately',
            'content' => 'Changed body.',
        ])->assertForbidden();

        $this->assertDatabaseCount('articles', 1);
        $this->assertSame('Private draft', $article->fresh()->title);
    }

    public function test_article_from_another_superblog_cannot_be_opened_or_edited(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();
        $otherOwner = $this->makeApprovedUser(superAdmin: true);
        $otherSuperblog = $this->makeSuperblog($otherOwner, 'Another Superblog');
        $article = $this->makeManualArticle($otherSuperblog, 'Another blog article');

        $this->actingAs($staff)
            ->get(route('admin.superblogs.articles.edit', [$superblog, $article]))
            ->assertNotFound();

        $this->put(route('admin.superblogs.articles.update', [$superblog, $article]), [
            'title' => 'Tampered title',
            'slug' => 'tampered-title',
            'content' => 'Tampered body.',
        ])->assertNotFound();

        $this->assertSame('Another blog article', $article->fresh()->title);
    }

    public function test_editing_manual_article_preserves_its_status_selection_and_publication_time(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();
        $publishedAt = now()->subDay()->startOfSecond();
        $article = $this->makeManualArticle($superblog, 'Published manual story', [
            'status' => 'published',
            'is_selected_for_publication' => false,
            'published_at' => $publishedAt,
        ]);

        $this->actingAs($staff)
            ->get(route('admin.superblogs.edit', [$superblog, 'flik' => 'innehall']))
            ->assertOk()
            ->assertSee(route('admin.superblogs.articles.edit', [$superblog, $article]), false)
            ->assertSee('Manuell');

        $this->put(route('admin.superblogs.articles.update', [$superblog, $article]), [
            'title' => 'Updated manual story',
            'slug' => 'updated-manual-story',
            'content' => 'Updated body.',
            'status' => 'draft',
            'is_selected_for_publication' => true,
            'published_at' => null,
        ])->assertRedirect(route('admin.superblogs.articles.edit', [$superblog, $article]));

        $article->refresh();
        $this->assertSame('Updated manual story', $article->title);
        $this->assertSame('manual', $article->source_type);
        $this->assertSame('published', $article->status);
        $this->assertFalse($article->is_selected_for_publication);
        $this->assertTrue($publishedAt->equalTo($article->published_at));
        $this->assertNotNull($article->content_modified_at);
    }

    public function test_new_manual_draft_can_be_found_and_edited_from_innehall(): void
    {
        [$staff, $superblog] = $this->makeAssignedSuperblog();
        $article = $this->makeManualArticle($superblog, 'Find me in Innehåll');

        $this->actingAs($staff)
            ->get(route('admin.superblogs.edit', [$superblog, 'flik' => 'skriv-artikel']))
            ->assertOk()
            ->assertSee(route('admin.superblogs.edit', [$superblog, 'flik' => 'innehall']), false);

        $this->get(route('admin.superblogs.articles.edit', [$superblog, $article]))
            ->assertOk()
            ->assertSee('Find me in Innehåll');
    }

    private function makeAssignedSuperblog(): array
    {
        $owner = $this->makeApprovedUser(superAdmin: true);
        $staff = $this->makeApprovedUser();
        $superblog = $this->makeSuperblog($owner, 'Manual article Superblog');
        $superblog->staff()->attach($staff->getKey());

        return [$staff, $superblog];
    }

    private function makeSuperblog(User $owner, string $name): Superblog
    {
        $template = Template::create([
            'name' => 'Manual article template '.$name,
            'slug' => str($name)->slug().'-manual-template',
            'layout' => 'layouts.public',
            'is_active' => true,
        ]);

        return $owner->superblogs()->create([
            'template_id' => $template->id,
            'name' => $name,
            'slug' => str($name)->slug(),
        ]);
    }

    private function makeManualArticle(Superblog $superblog, string $title, array $attributes = []): Article
    {
        return $superblog->articles()->create(array_merge([
            'title' => $title,
            'slug' => str($title)->slug(),
            'content' => 'Article body.',
            'source_type' => 'manual',
            'status' => 'draft',
            'is_selected_for_publication' => false,
        ], $attributes));
    }

    private function document(string $introduction): string
    {
        return json_encode([
            'version' => 1,
            'blocks' => [[
                'id' => (string) Str::uuid(),
                'type' => 'introduction',
                'runs' => [['text' => $introduction]],
            ]],
        ], JSON_THROW_ON_ERROR);
    }

    private function articleIntroduction(Article $article): string
    {
        $document = (new ArticleContentDocument)->readFrom($article);

        return implode('', array_column($document['blocks'][0]['runs'] ?? [], 'text'));
    }

    private function documentWithBlocks(array $bodyBlocks, bool $includeIntroduction = true): string
    {
        $blocks = $includeIntroduction
            ? [['type' => 'introduction', 'runs' => [['text' => 'Introduction text.']]], ...$bodyBlocks]
            : $bodyBlocks;

        foreach ($blocks as &$block) {
            $block['id'] = (string) Str::uuid();
            if (($block['type'] ?? null) === 'list') {
                foreach ($block['items'] as &$item) {
                    $item['id'] = (string) Str::uuid();
                }
                unset($item);
            }
        }
        unset($block);

        return json_encode(['version' => 1, 'blocks' => $blocks], JSON_THROW_ON_ERROR);
    }

    private function makeApprovedUser(bool $superAdmin = false): User
    {
        $user = User::factory()->create();
        $user->approval_status = 'approved';
        $user->is_super_admin = $superAdmin;
        $user->save();

        return $user;
    }
}
