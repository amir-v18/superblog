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
        $this->assertSame('techenspraket', Theme::forTemplate(new Template(['slug' => 'tech'])));

        // Templates without a mapping get the grey fallback thumbnail.
        $this->assertNull(Theme::forTemplate(new Template(['slug' => 'editorial-default'])));
        $this->assertNull(Theme::forTemplate(new Template(['slug' => 'klassisk'])));

        foreach (Theme::TEMPLATE_THEMES as $theme) {
            $this->assertContains($theme, Theme::NAMES);
        }
    }
}
