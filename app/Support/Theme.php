<?php

namespace App\Support;

use App\Models\Superblog;
use App\Models\Template;
use Illuminate\Support\Facades\Log;

/**
 * Turns a theme (a theme name, or an array of overrides) into safe values for the <html> tag.
 *
 * Values end up inside a style attribute, and Blade escaping does not protect against CSS injection,
 * so only allowlisted keys, strict #rrggbb colors and allowlisted fonts are ever output.
 */
class Theme
{
    /** Themes defined in resources/css/app.css as [data-theme='...'] blocks. */
    public const NAMES = ['bondebladet', 'techenspraket'];

    /**
     * Which theme draws a template's thumbnail in the create modal, keyed on the template's slug.
     * A slug missing here (or a renamed one) gets the grey fallback thumbnail instead of breaking.
     */
    public const TEMPLATE_THEMES = ['modern' => 'bondebladet', 'tech' => 'techenspraket'];

    public const COLOR_KEYS = ['paper', 'white', 'ink', 'muted', 'primary', 'deep', 'line', 'pale'];

    public const FONT_KEYS = ['display', 'body'];

    /** Allowed font families (installed via @fontsource-variable) mapped to their fallback stacks. */
    public const FONTS = [
        'Newsreader Variable' => 'ui-serif, Georgia, serif',
        'DM Sans Variable' => 'ui-sans-serif, system-ui, sans-serif',
        'Space Grotesk Variable' => 'ui-sans-serif, system-ui, sans-serif',
    ];

    /**
     * The base theme name for data-theme: the string itself, or the array's 'name' key.
     * Not logged: it runs on every render (sidebar, cards), and saving is already validated against NAMES.
     */
    public static function name(string|array|null $theme): ?string
    {
        $name = is_array($theme) ? ($theme['name'] ?? null) : $theme;

        return in_array($name, self::NAMES, true) ? $name : null;
    }

    /**
     * The magazine's saved theme name. Null means "no theme chosen": the admin shows that state
     * (grey dot, no thumbnail, nothing preselected), so there is deliberately no fallback here.
     */
    public static function nameFor(Superblog $superblog): ?string
    {
        return self::name($superblog->settings['theme'] ?? null);
    }

    /**
     * The thumbnail theme for a template, or null for the grey fallback. Goes through name(), so a mapped
     * theme that is later removed from NAMES also falls back instead of reaching data-theme.
     */
    public static function forTemplate(Template $template): ?string
    {
        return self::name(self::TEMPLATE_THEMES[$template->slug] ?? null);
    }

    /**
     * CSS custom properties for the style attribute, e.g. "--paper: #f8f7f2; --primary: #1b4b37;".
     * Invalid values are skipped (and logged) so a bad setting never breaks a public page.
     */
    public static function cssVariables(string|array|null $theme): string
    {
        if (! is_array($theme)) {
            return '';
        }

        $variables = [];

        foreach ($theme as $key => $value) {
            if ($key === 'name') {
                continue;
            }

            $css = self::cssValue($key, $value);

            if ($css === null) {
                Log::warning('Ignored invalid theme value.', ['key' => $key, 'value' => $value]);

                continue;
            }

            $variables[] = "--{$key}: {$css};";
        }

        return implode(' ', $variables);
    }

    private static function cssValue(int|string $key, mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        // \z instead of $ so a trailing newline cannot slip through.
        if (in_array($key, self::COLOR_KEYS, true)) {
            return preg_match('/^#[0-9a-fA-F]{6}\z/', $value) ? $value : null;
        }

        if (in_array($key, self::FONT_KEYS, true)) {
            return isset(self::FONTS[$value]) ? "'{$value}', ".self::FONTS[$value] : null;
        }

        return null;
    }
}
