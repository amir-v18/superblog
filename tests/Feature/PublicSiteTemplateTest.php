<?php

namespace Tests\Feature;

use App\Models\Superblog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Concerns\CreatesSuperblogs;
use Tests\TestCase;

/**
 * The public front page picks its site from config/templates.php, the same way as the builder's preview,
 * so a published magazine looks like what was previewed.
 */
class PublicSiteTemplateTest extends TestCase
{
    use CreatesSuperblogs, RefreshDatabase;

    public function test_a_published_classic_magazine_shows_its_own_front_page_and_articles(): void
    {
        $superblog = $this->publishedMagazine('template-classic');
        $this->makeArticle($superblog, 'Lead story', now()->subMinute());
        $this->makeArticle($superblog, 'News story', now()->subMinutes(2));

        $content = $this->get(route('public.superblogs.show', $superblog))
            ->assertOk()
            ->assertViewIs('templates.classic.home')
            ->assertSee('data-theme="budbararen"', false)
            ->assertSee('Lead story')
            ->assertSee('News story')
            ->assertHeaderMissing('X-Robots-Tag')
            ->assertDontSee('<meta name="robots"', false)
            ->assertDontSee('data-admin-preview-superblog-id', false)
            ->getContent();

        $this->assertSame(1, substr_count($content, '<h1'));
    }

    public function test_the_classic_front_page_loads_the_newspaper_fonts(): void
    {
        $superblog = $this->publishedMagazine('template-classic');

        $response = $this->get(route('public.superblogs.show', $superblog))->assertOk();

        // The fonts directive writes the @font-face rules into the page. Only the newspaper fonts are named,
        // so the admin's own font stays out.
        foreach (['UnifrakturMaguntia', 'Playfair Display', 'Old Standard TT', 'IM Fell English SC'] as $family) {
            $response->assertSee('font-family: "'.$family.'"', false);
        }
        $response->assertDontSee('Instrument Sans', false);
    }

    public function test_template_rendering_uses_its_stable_key_after_name_and_slug_are_renamed(): void
    {
        $superblog = $this->publishedMagazine('template-classic');
        $superblog->template->update(['name' => 'Renamed newspaper', 'slug' => 'different-slug']);

        $this->get(route('public.superblogs.show', $superblog))
            ->assertOk()
            ->assertViewIs('templates.classic.home')
            ->assertSee('data-theme="budbararen"', false);
    }

    public function test_an_inactive_template_stays_available_to_its_existing_superblog(): void
    {
        $superblog = $this->publishedMagazine('template-classic');
        $superblog->template->update(['is_active' => false]);

        $this->get(route('public.superblogs.show', $superblog))
            ->assertOk()
            ->assertViewIs('templates.classic.home')
            ->assertSee('data-theme="budbararen"', false);
    }

    public function test_only_selected_and_dated_articles_reach_the_public_classic_page(): void
    {
        $superblog = $this->publishedMagazine('template-classic');
        $this->makeArticle($superblog, 'Shown story', now());
        $this->makeArticle($superblog, 'Unselected story', now(), selected: false);
        $this->makeArticle($superblog, 'Undated story', null);
        $this->makeArticle($superblog, 'Draft story', null, status: 'draft');

        $this->get(route('public.superblogs.show', $superblog))
            ->assertOk()
            ->assertViewIs('templates.classic.home')
            ->assertSee('Shown story')
            ->assertDontSee('Unselected story')
            ->assertDontSee('Undated story')
            ->assertDontSee('Draft story')
            // Drafts only ever reach the builder's preview.
            ->assertDontSee('>Utkast<', false);
    }

    public function test_homepage_article_dates_match_the_article_view_and_stack_vertically(): void
    {
        $superblog = $this->publishedMagazine('template-modern');
        $this->makeArticle($superblog, 'Dated story', now());
        $article = $superblog->articles()->firstOrFail();
        $createdAt = now()->subDay()->startOfDay();
        $modifiedAt = $createdAt->copy()->addHours(4);
        $article->forceFill([
            'created_at' => $createdAt,
            'content_modified_at' => $modifiedAt,
        ])->save();

        foreach (['template-modern', 'template-classic'] as $templateKey) {
            $superblog->template->forceFill(['seed_key' => $templateKey])->save();
            $content = $this->get(route('public.superblogs.show', $superblog))
                ->assertOk()
                ->assertSee('Skapad:')
                ->assertSee('Senast ändrad:')
                ->assertSee($createdAt->locale('sv')->isoFormat('D MMM YYYY'))
                ->assertSee($modifiedAt->locale('sv')->isoFormat('D MMM YYYY'))
                ->assertSee('datetime="'.$createdAt->toIso8601String().'"', false)
                ->assertSee('datetime="'.$modifiedAt->toIso8601String().'"', false)
                ->getContent();

            $this->assertMatchesRegularExpression(
                '/<div class="grid gap-y-0\.5[^\"]*">\s*<div>\s*<span>Skapad:<\/span>.*?<\/div>\s*<div>\s*<span>Senast ändrad:<\/span>/s',
                $content,
            );
        }
    }

    public function test_new_homepage_articles_show_only_the_created_date(): void
    {
        $superblog = $this->publishedMagazine('template-modern');
        $this->makeArticle($superblog, 'New story', now());

        foreach (['template-modern', 'template-classic'] as $templateKey) {
            $superblog->template->forceFill(['seed_key' => $templateKey])->save();
            $this->get(route('public.superblogs.show', $superblog))
                ->assertOk()
                ->assertSee('Skapad:')
                ->assertDontSee('Senast ändrad:');
        }
    }

    public function test_the_article_page_still_works_for_a_classic_magazine(): void
    {
        $superblog = $this->publishedMagazine('template-classic');
        $this->makeArticle($superblog, 'Lead story', now());
        $article = $superblog->articles()->firstOrFail();

        // The article page is shared by all templates for now, so it only takes the magazine's colours.
        $this->get(route('public.superblogs.articles.show', [$superblog, $article]))
            ->assertOk()
            ->assertViewIs('templates.news.article')
            ->assertSee('Lead story');
    }

    public function test_article_view_is_resolved_by_stable_key_after_the_slug_changes(): void
    {
        $superblog = $this->publishedMagazine('template-classic');
        $superblog->template->update(['name' => 'Renamed classic', 'slug' => 'edited-slug']);
        $this->makeArticle($superblog, 'Lead story', now());
        $article = $superblog->articles()->firstOrFail();
        config(['templates.list.template-classic.article_view' => 'templates.keyed-article-check']);

        $this->withoutExceptionHandling();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('View [templates.keyed-article-check] not found.');

        $this->get(route('public.superblogs.articles.show', [$superblog, $article]));
    }

    public function test_an_unpublished_classic_magazine_is_not_found(): void
    {
        $superblog = $this->publishedMagazine('template-classic');
        $superblog->update(['status' => 'draft']);

        $this->get(route('public.superblogs.show', $superblog))->assertNotFound();
    }

    private function publishedMagazine(string $templateKey): Superblog
    {
        $superblog = $this->makeSuperblog($this->makeUser(approved: true, superAdmin: true));
        $superblog->template->forceFill(['seed_key' => $templateKey])->save();
        $superblog->update(['status' => 'published']);

        return $superblog;
    }

    private function makeArticle(Superblog $superblog, string $title, mixed $publishedAt, string $status = 'published', bool $selected = true): void
    {
        $superblog->articles()->create([
            'title' => $title,
            'slug' => str($title)->slug(),
            'content' => "<p>{$title} content.</p>",
            'source_type' => 'manual',
            'status' => $status,
            'is_selected_for_publication' => $selected,
            'published_at' => $publishedAt,
        ]);
    }
}
