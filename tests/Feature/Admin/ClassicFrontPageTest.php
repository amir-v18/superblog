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

    public function test_articles_fill_the_lead_the_news_and_the_row_in_order(): void
    {
        [$admin, $superblog] = $this->classicMagazine('published');
        // Story 1 is the newest. Each one is a minute older than the one before.
        foreach (range(1, 7) as $number) {
            $this->makeArticle($superblog, "Story {$number}", Carbon::parse('2026-11-09 14:00')->subMinutes($number), excerpt: "Ingress {$number}");
        }
        $this->makeArticle($superblog, 'Unselected story', now(), selected: false);

        $html = $this->preview($admin, $superblog);
        $lead = $this->part($html, '<section aria-label="Huvudartikel"');
        $news = $this->part($html, '<section aria-labelledby="news-heading"');
        $row = $this->part($html, '<section aria-labelledby="more-heading"');

        $this->assertStringContainsString('Story 1<', $lead);
        $this->assertStringContainsString('Ingress 1', $lead);
        $this->assertTrue($this->inOrder($news, ['Story 2<', 'Ingress 2', 'Story 3<', 'Ingress 3']));
        $this->assertTrue($this->inOrder($row, ['Story 4<', 'Story 5<', 'Story 6<', 'Story 7<']));
        // Exactly seven articles fill the first row, so no extra row follows.
        $this->assertSame(1, substr_count($row, 'grid grid-cols-4'));
        $this->assertSame(4, substr_count($row, '<article'));
        // The row shows headlines and dates only.
        $this->assertStringNotContainsString('Ingress 4', $row);
        $this->assertStringContainsString('>9 november 2026</time>', $lead);

        // Every slot is taken, so no stand-ins are left, and the "Mest lästa" list is full as well.
        foreach (['Här visas din första artikel', 'Av redaktionen', 'rounded-[3px] bg-ink/14', 'Manuella artiklar visas här'] as $standIn) {
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

    public function test_articles_after_the_seventh_get_more_rows_of_four_without_stand_ins(): void
    {
        [$admin, $superblog] = $this->classicMagazine('published');
        foreach (range(1, 18) as $number) {
            $this->makeArticle($superblog, "Story {$number}", now()->subMinutes($number));
        }

        $row = $this->part($this->preview($admin, $superblog), '<section aria-labelledby="more-heading"');

        // The first row of four (4–7), then 8–11, 12–15 and a last row with only 16–18: no selected article is left off.
        $this->assertTrue($this->inOrder($row, array_map(fn ($number) => "Story {$number}<", range(4, 18))));
        $this->assertSame(4, substr_count($row, 'grid grid-cols-4'));
        $this->assertSame(15, substr_count($row, '<article'));
        $this->assertStringNotContainsString('rounded-[3px] bg-ink/14', $row);
    }

    public function test_most_read_lists_the_newest_published_manual_articles_numbered(): void
    {
        [$admin, $superblog] = $this->classicMagazine('published');
        // The same articles as the Modern front page: manual, published, selected and dated, at most five, newest first.
        foreach (range(1, 6) as $number) {
            $this->makeArticle($superblog, "Manual {$number}", now()->subMinutes($number));
        }
        $this->makeArticle($superblog, 'From a feed', now(), source: 'rss');
        $this->makeArticle($superblog, 'Not selected', now(), selected: false);

        $mostRead = $this->part($this->preview($admin, $superblog), '<section aria-labelledby="most-read-heading"');

        $this->assertStringContainsString('>Mest lästa</h2>', $mostRead);
        $this->assertTrue($this->inOrder($mostRead, ['>1<', 'Manual 1<', '>2<', 'Manual 2<', '>3<', 'Manual 3<', '>4<', 'Manual 4<', '>5<', 'Manual 5<']));
        $this->assertSame(5, substr_count($mostRead, '<li'));
        foreach (['Manual 6', 'From a feed', 'Not selected', 'rounded-[3px] bg-ink/14', '<time'] as $missing) {
            $this->assertStringNotContainsString($missing, $mostRead);
        }
        // The list is numbered for screen readers already, so the printed numbers are skipped by them.
        $this->assertSame(5, substr_count($mostRead, '<span aria-hidden="true"'));

        $manual1 = Article::where('title', 'Manual 1')->firstOrFail();
        $this->assertStringContainsString('href="'.route('public.superblogs.articles.show', [$superblog, $manual1]).'" target="_blank" rel="noopener"', $mostRead);
    }

    public function test_most_read_explains_itself_when_there_are_articles_but_no_manual_ones(): void
    {
        [$admin, $superblog] = $this->classicMagazine('published');
        $this->makeArticle($superblog, 'From a feed', now(), source: 'rss');

        $mostRead = $this->part($this->preview($admin, $superblog), '<section aria-labelledby="most-read-heading"');

        // The same text as the Modern front page, and no stand-ins, since the magazine does have articles.
        $this->assertStringContainsString('Manuella artiklar visas här när de publiceras.', $mostRead);
        $this->assertStringNotContainsString('<ol', $mostRead);
        $this->assertStringNotContainsString('From a feed', $mostRead);
    }

    public function test_slots_without_an_article_keep_their_stand_ins(): void
    {
        [$admin, $superblog] = $this->classicMagazine('published');
        foreach (range(1, 3) as $number) {
            $this->makeArticle($superblog, "Story {$number}", now()->subMinutes($number));
        }

        $html = $this->preview($admin, $superblog);
        $mostRead = $this->part($html, '<section aria-labelledby="most-read-heading"');
        $row = $this->part($html, '<section aria-labelledby="more-heading"');

        // The lead and both news items are filled.
        $this->assertStringContainsString('Story 1<', $this->part($html, '<section aria-label="Huvudartikel"'));
        $this->assertStringNotContainsString('Här visas din första artikel', $html);
        $this->assertStringNotContainsString('Av redaktionen', $html);
        // Without an excerpt there is no empty line under the headline.
        $this->assertStringNotContainsString('italic">', $html);

        // The row is still empty: four stand-ins without headlines.
        $this->assertStringNotContainsString('<h3', $row);
        $this->assertSame(4, substr_count($row, '<article'));
        // "Mest lästa" shows the three it has, without stand-ins for the rest, as on the Modern front page.
        $this->assertSame(3, substr_count($mostRead, '<li'));
        $this->assertStringNotContainsString('rounded-[3px] bg-ink/14', $mostRead);
        $this->assertNothingIsMadeUp($html);
    }

    public function test_an_empty_magazine_shows_only_stand_ins(): void
    {
        [$admin, $superblog] = $this->classicMagazine('draft');

        $html = $this->preview($admin, $superblog);
        $mostRead = $this->part($html, '<section aria-labelledby="most-read-heading"');

        $this->assertStringContainsString('Här visas din första artikel', $html);
        $this->assertSame(2, substr_count($html, 'Av redaktionen'));
        $this->assertStringNotContainsString('<h3', $html);
        $this->assertStringNotContainsString(route('public.superblogs.show', $superblog).'/articles', $html);
        // Five numbered stand-ins in "Mest lästa", as on the Modern front page.
        $this->assertSame(5, substr_count($mostRead, '<li'));
        $this->assertTrue($this->inOrder($mostRead, ['>1<', '>2<', '>3<', '>4<', '>5<']));
        $this->assertStringNotContainsString('<a ', $mostRead);
        $this->assertStringNotContainsString('Manuella artiklar visas här', $mostRead);
        $this->assertNothingIsMadeUp($html);
        // Without articles the magazine name is still the single H1, and it sits in both the H1 and the
        // footer, where both follow the builder's name field.
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertSame(2, substr_count($html, 'data-live-name'));
    }

    public function test_selected_drafts_show_in_the_offline_preview_without_a_made_up_time(): void
    {
        [$admin, $superblog] = $this->classicMagazine('draft');
        // Undated drafts are sorted by id, newest first: draft 4 leads and draft 1 opens the row.
        foreach (range(1, 4) as $number) {
            $this->makeArticle($superblog, "Draft {$number}", null, status: 'draft');
        }

        $html = $this->preview($admin, $superblog);
        $row = $this->part($html, '<section aria-labelledby="more-heading"');

        $this->assertStringContainsString('Draft 4<', $this->part($html, '<section aria-label="Huvudartikel"'));
        $this->assertStringContainsString('Draft 1<', $row);
        $this->assertStringContainsString('>Utkast</span>', $row);
        $this->assertSame(4, substr_count($html, '>Utkast</span>'));
        $this->assertStringNotContainsString('<time datetime="', $this->part($html, '<main'));
        // Drafts are not published, so "Mest lästa" has none of them, as on the Modern front page.
        $this->assertStringContainsString('Manuella artiklar visas här när de publiceras.', $this->part($html, '<section aria-labelledby="most-read-heading"'));
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

    private function makeArticle(Superblog $superblog, string $title, ?Carbon $publishedAt, ?string $excerpt = null, string $status = 'published', bool $selected = true, string $source = 'manual'): Article
    {
        return $superblog->articles()->create([
            'title' => $title,
            'slug' => str($title)->slug(),
            'content' => "<p>{$title} content.</p>",
            'excerpt' => $excerpt,
            'source_type' => $source,
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
