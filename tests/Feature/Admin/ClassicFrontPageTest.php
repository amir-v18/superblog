<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\Superblog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesSuperblogs;
use Tests\TestCase;

/**
 * The Klassisk tidning front page in the builder's preview: real articles fill the slots in reading order,
 * and every slot without one keeps its stand-in, so nothing is ever made up.
 */
class ClassicFrontPageTest extends TestCase
{
    use CreatesSuperblogs, RefreshDatabase;

    /** Sample texts from the design that must never reach a real page. */
    private const DESIGN_SAMPLES = ['5 kronor', 'Vädret', 'Pris', 'Årg.', 'Grundad', 'Prenumerera', 'Annons', 'Små annonser', 'Allmän Tidning', 'Budbäraren', 'Fortsättning', 'vår medarbetare'];

    public function test_articles_fill_the_lead_the_news_the_telegrams_and_the_row_in_order(): void
    {
        [$admin, $superblog] = $this->classicMagazine('published');
        // Story 1 is the newest. Each one is a minute older than the one before.
        foreach (range(1, 12) as $number) {
            $this->makeArticle($superblog, "Story {$number}", Carbon::parse('2026-11-09 14:00')->subMinutes($number), excerpt: "Ingress {$number}");
        }
        $this->makeArticle($superblog, 'Unselected story', now(), selected: false);

        $html = $this->preview($admin, $superblog);
        $lead = $this->part($html, '<section aria-label="Huvudartikel"');
        $news = $this->part($html, '<section aria-labelledby="news-heading"');
        $telegrams = $this->part($html, '<section aria-labelledby="telegram-heading"');
        $row = $this->part($html, '<section aria-labelledby="more-heading"');

        $this->assertStringContainsString('Story 1<', $lead);
        $this->assertStringContainsString('Ingress 1', $lead);
        $this->assertTrue($this->inOrder($news, ['Story 2<', 'Ingress 2', 'Story 3<', 'Ingress 3']));
        $this->assertTrue($this->inOrder($telegrams, ['Story 4<', 'Story 5<', 'Story 6<', 'Story 7<', 'Story 8<']));
        $this->assertTrue($this->inOrder($row, ['Story 9<', 'Story 10<', 'Story 11<', 'Story 12<']));
        // The row shows headlines and dates only.
        $this->assertStringNotContainsString('Ingress 9', $row);

        // Telegrams show the time of day; the others the date.
        $this->assertStringContainsString('>13.56</time>', $telegrams);
        $this->assertStringContainsString('>9 november 2026</time>', $lead);

        // Every slot is taken, so no stand-ins are left.
        foreach (['Här visas din första artikel', 'Av redaktionen', '>00.00<'] as $standIn) {
            $this->assertStringNotContainsString($standIn, $html);
        }
        $this->assertStringNotContainsString('Unselected story', $html);
        $this->assertNothingIsMadeUp($html);

        // The magazine name stays the single H1; the lead story is an H2 and the others H3.
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertSame(1, substr_count($lead, '<h2'));
        $this->assertSame(2, substr_count($html, 'data-live-name'));

        // Links go to the public article page, in a new tab, as on the Modern front page.
        $story1 = Article::where('title', 'Story 1')->firstOrFail();
        $this->assertStringContainsString('href="'.route('public.superblogs.articles.show', [$superblog, $story1]).'" target="_blank" rel="noopener"', $lead);
    }

    public function test_slots_without_an_article_keep_their_stand_ins(): void
    {
        [$admin, $superblog] = $this->classicMagazine('published');
        foreach (range(1, 3) as $number) {
            $this->makeArticle($superblog, "Story {$number}", now()->subMinutes($number));
        }

        $html = $this->preview($admin, $superblog);
        $telegrams = $this->part($html, '<section aria-labelledby="telegram-heading"');
        $row = $this->part($html, '<section aria-labelledby="more-heading"');

        // The lead and both news items are filled.
        $this->assertStringContainsString('Story 1<', $this->part($html, '<section aria-label="Huvudartikel"'));
        $this->assertStringNotContainsString('Här visas din första artikel', $html);
        $this->assertStringNotContainsString('Av redaktionen', $html);
        // Without an excerpt there is no empty line under the headline.
        $this->assertStringNotContainsString('italic">', $html);

        // Telegrams and the row are still empty.
        $this->assertSame(5, substr_count($telegrams, '>00.00<'));
        $this->assertStringNotContainsString('<a ', $telegrams);
        $this->assertStringNotContainsString('<h3', $row);
        $this->assertNothingIsMadeUp($html);
    }

    public function test_an_empty_magazine_shows_only_stand_ins(): void
    {
        [$admin, $superblog] = $this->classicMagazine('draft');

        $html = $this->preview($admin, $superblog);

        $this->assertStringContainsString('Här visas din första artikel', $html);
        $this->assertSame(2, substr_count($html, 'Av redaktionen'));
        $this->assertSame(5, substr_count($html, '>00.00<'));
        $this->assertStringNotContainsString('<h3', $html);
        $this->assertStringNotContainsString(route('public.superblogs.show', $superblog).'/articles', $html);
        $this->assertNothingIsMadeUp($html);
    }

    public function test_selected_drafts_show_in_the_offline_preview_without_a_made_up_time(): void
    {
        [$admin, $superblog] = $this->classicMagazine('draft');
        // Undated drafts are sorted by id, newest first: draft 4 leads and draft 1 is the first telegram.
        foreach (range(1, 4) as $number) {
            $this->makeArticle($superblog, "Draft {$number}", null, status: 'draft');
        }

        $html = $this->preview($admin, $superblog);
        $telegrams = $this->part($html, '<section aria-labelledby="telegram-heading"');

        $this->assertStringContainsString('Draft 4<', $this->part($html, '<section aria-label="Huvudartikel"'));
        $this->assertStringContainsString('Draft 1<', $telegrams);
        $this->assertStringContainsString('>Utkast</span>', $telegrams);
        $this->assertSame(4, substr_count($html, '>Utkast</span>'));
        $this->assertStringNotContainsString('<time datetime="', $this->part($html, '<main'));
    }

    /** @return array{0: User, 1: Superblog} */
    private function classicMagazine(string $status): array
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $superblog = $this->makeSuperblog($admin);
        $superblog->template->update(['slug' => 'classic']);
        $superblog->update(['status' => $status]);

        return [$admin, $superblog];
    }

    private function makeArticle(Superblog $superblog, string $title, ?Carbon $publishedAt, ?string $excerpt = null, string $status = 'published', bool $selected = true): Article
    {
        return $superblog->articles()->create([
            'title' => $title,
            'slug' => str($title)->slug(),
            'content' => "<p>{$title} content.</p>",
            'excerpt' => $excerpt,
            'source_type' => 'manual',
            'status' => $status,
            'is_selected_for_publication' => $selected,
            'published_at' => $publishedAt,
        ]);
    }

    private function preview(User $admin, Superblog $superblog): string
    {
        return $this->actingAs($admin)->get(route('admin.superblogs.preview', $superblog))
            ->assertOk()
            ->assertViewIs('templates.classic.home')
            ->getContent();
    }

    /** One part of the page, from its opening tag to the matching closing tag of the same name. */
    private function part(string $html, string $opening): string
    {
        $tag = substr(strtok($opening, ' '), 1);
        $this->assertSame(1, preg_match('#'.preg_quote($opening, '#').'.*?</'.$tag.'>#s', $html, $match), "Missing {$opening}");

        return $match[0];
    }

    /** @param  list<string>  $needles */
    private function inOrder(string $haystack, array $needles): bool
    {
        $position = -1;
        foreach ($needles as $needle) {
            $next = strpos($haystack, $needle);
            if ($next === false || $next <= $position) {
                return false;
            }
            $position = $next;
        }

        return true;
    }

    private function assertNothingIsMadeUp(string $html): void
    {
        foreach (self::DESIGN_SAMPLES as $sample) {
            $this->assertStringNotContainsString($sample, $html);
        }
    }
}
