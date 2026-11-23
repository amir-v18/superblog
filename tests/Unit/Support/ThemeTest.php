<?php

namespace Tests\Unit\Support;

use App\Models\Template;
use App\Support\Theme;
use Tests\TestCase;

class ThemeTest extends TestCase
{
    public function test_templates_map_to_their_thumbnail_theme_by_stable_key(): void
    {
        // Unsaved models: only the stable key is read, so no database is needed.
        $this->assertSame('bondebladet', Theme::forTemplate($this->templateWithKey('template-modern')));
        $this->assertSame('budbararen', Theme::forTemplate($this->templateWithKey('template-classic')));

        // Unknown keys get the grey fallback; editable slugs have no effect.
        $this->assertNull(Theme::forTemplate($this->templateWithKey('template-unknown', 'modern')));
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

    public function test_the_theme_comes_from_the_registry_even_when_the_slug_changes(): void
    {
        config(['templates.list.template-modern.theme' => 'techenspraket']);

        $this->assertSame('techenspraket', Theme::forTemplate($this->templateWithKey('template-modern', 'renamed-slug')));
    }

    private function templateWithKey(string $key, ?string $slug = null): Template
    {
        $template = new Template;
        $template->forceFill(['seed_key' => $key, 'slug' => $slug]);

        return $template;
    }
}
