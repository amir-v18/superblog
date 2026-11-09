<?php

namespace Tests\Feature;

use App\Models\Superblog;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $superblog = $this->publishedMagazine('classic');
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
        $superblog = $this->publishedMagazine('classic');

        $response = $this->get(route('public.superblogs.show', $superblog))->assertOk();

        // The fonts directive writes the @font-face rules into the page. Only the newspaper fonts are named,
        // so the admin's own font stays out.
        foreach (['UnifrakturMaguntia', 'Playfair Display', 'Old Standard TT', 'IM Fell English SC'] as $family) {
            $response->assertSee('font-family: "'.$family.'"', false);
        }
        $response->assertDontSee('Instrument Sans', false);
    }

    public function test_modern_and_templates_missing_from_the_registry_keep_the_modern_front_page(): void
    {
        // makeSuperblog's template has the slug "news", which is not in the registry.
        $superblog = $this->publishedMagazine('news');

        $this->get(route('public.superblogs.show', $superblog))
            ->assertOk()
            ->assertViewIs('templates.news.home');

        $superblog->template->update(['slug' => 'modern']);

        $this->get(route('public.superblogs.show', $superblog))
            ->assertOk()
            ->assertViewIs('templates.news.home');
    }

    public function test_only_selected_and_dated_articles_reach_the_public_classic_page(): void
    {
        $superblog = $this->publishedMagazine('classic');
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

    public function test_the_article_page_still_works_for_a_classic_magazine(): void
    {
        $superblog = $this->publishedMagazine('classic');
        $this->makeArticle($superblog, 'Lead story', now());
        $article = $superblog->articles()->firstOrFail();
        // With an excerpt, since the article page cannot render an article without one yet.
        $article->update(['excerpt' => 'Lead excerpt']);

        // The article page is shared by all templates for now, so it only takes the magazine's colours.
        $this->get(route('public.superblogs.articles.show', [$superblog, $article]))
            ->assertOk()
            ->assertViewIs('templates.news.article')
            ->assertSee('Lead story');
    }

    public function test_an_unpublished_classic_magazine_is_not_found(): void
    {
        $superblog = $this->publishedMagazine('classic');
        $superblog->update(['status' => 'draft']);

        $this->get(route('public.superblogs.show', $superblog))->assertNotFound();
    }

    private function publishedMagazine(string $templateSlug): Superblog
    {
        $superblog = $this->makeSuperblog($this->makeUser(approved: true, superAdmin: true));
        $superblog->template->update(['slug' => $templateSlug]);
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
