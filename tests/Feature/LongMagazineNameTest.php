<?php

namespace Tests\Feature;

use App\Models\Superblog;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSuperblogs;
use Tests\TestCase;

/**
 * A long magazine name, with or without spaces, must never make a page scroll sideways. A test cannot measure
 * widths, so it checks that every place showing the name in large type may break it; the widths themselves were
 * checked in a browser at 1280 and 390 px.
 */
class LongMagazineNameTest extends TestCase
{
    use CreatesSuperblogs, RefreshDatabase;

    private const NAMES = [
        'Nordvästra Skånes Allmänna Tidning för Handel, Sjöfart och Kultur',
        'Donaudampfskeppsbolagetsnyhetsbladförkaptener',
    ];

    public function test_the_classic_front_page_breaks_a_long_name(): void
    {
        foreach (self::NAMES as $name) {
            [$admin, $superblog] = $this->magazine('classic', $name);
            $html = $this->actingAs($admin)->get(route('admin.superblogs.preview', $superblog))->assertOk()->getContent();

            $h1 = $this->openingTag($html, '<h1');
            $this->assertStringContainsString('wrap-anywhere', $h1, $name);
            // Kept on one line, the name ran far past the sheet, on phones even for a normal name.
            $this->assertStringNotContainsString('whitespace-nowrap', $h1, $name);
            $this->assertStringContainsString('wrap-anywhere', $this->openingTag($html, '<footer', '<p'), $name);
        }
    }

    public function test_the_modern_front_page_breaks_a_long_name(): void
    {
        foreach (self::NAMES as $name) {
            [$admin, $superblog] = $this->magazine('modern', $name);
            $html = $this->actingAs($admin)->get(route('admin.superblogs.preview', $superblog))->assertOk()->getContent();

            $this->assertStringContainsString('wrap-anywhere', $this->openingTag($html, '<h1'), $name);
            $this->assertStringContainsString('wrap-anywhere', $this->openingTag($html, '<footer', '<p'), $name);
            $this->assertStringContainsString('wrap-anywhere', $this->openingTag($html, '<footer', '<small'), $name);
        }
    }

    public function test_the_dashboard_card_breaks_a_long_name_and_keeps_its_status(): void
    {
        [$admin, $superblog] = $this->magazine('modern', self::NAMES[1]);

        $html = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<h3 class="min-w-0 [^"]*wrap-anywhere[^"]*">'.preg_quote(self::NAMES[1], '#').'</h3>#u', $html);
    }

    /** @return array{0: User, 1: Superblog} */
    private function magazine(string $slug, string $name): array
    {
        $admin = $this->makeUser(approved: true, superAdmin: true);
        $template = Template::firstOrCreate(['slug' => $slug], ['name' => $slug, 'layout' => 'layouts.public', 'is_active' => true]);
        $superblog = $admin->superblogs()->create([
            'template_id' => $template->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.$slug,
        ]);

        return [$admin, $superblog];
    }

    /** The first opening tag $tag, optionally the first one after $after. */
    private function openingTag(string $html, string $after, ?string $tag = null): string
    {
        $tag ??= $after;
        $from = strpos($html, $after);
        $this->assertNotFalse($from, "Missing {$after}");
        $this->assertSame(1, preg_match('#'.preg_quote($tag, '#').'\b[^>]*>#', $html, $match, 0, $from), "Missing {$tag}");

        return $match[0];
    }
}
