<?php

namespace Tests\Unit\Support;

use App\Models\Template;
use App\Support\Theme;
use Tests\TestCase;

class ThemeTest extends TestCase
{
    public function test_templates_map_to_their_thumbnail_theme_by_slug(): void
    {
        // Unsaved models: only the slug is read, so no database is needed.
        $this->assertSame('bondebladet', Theme::forTemplate(new Template(['slug' => 'modern'])));
        $this->assertSame('budbararen', Theme::forTemplate(new Template(['slug' => 'classic'])));

        // Slugs missing from the registry get the grey fallback.
        $this->assertNull(Theme::forTemplate(new Template(['slug' => 'tech'])));
        $this->assertNull(Theme::forTemplate(new Template(['slug' => 'editorial-default'])));
        $this->assertNull(Theme::forTemplate(new Template));

        foreach (config('templates.list') as $entry) {
            if ($entry['theme'] !== null) {
                $this->assertContains($entry['theme'], Theme::NAMES);
            }
        }
    }

    public function test_no_template_gives_no_theme(): void
    {
        // Callers pass a template that may be missing (an id that matches no row, or a magazine without one),
        // so null must give null rather than an error, leaving the fallback to them.
        $this->assertNull(Theme::forTemplate(null));
    }

    public function test_the_theme_comes_from_the_registry(): void
    {
        config(['templates.list.modern.theme' => 'techenspraket']);

        $this->assertSame('techenspraket', Theme::forTemplate(new Template(['slug' => 'modern'])));
    }
}
