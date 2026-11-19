<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

class ArticleTextSizeAttributeSanitizer implements AttributeSanitizerInterface
{
    private const VALUES = ['small', 'normal', 'large'];

    public function getSupportedElements(): ?array
    {
        return ['span'];
    }

    public function getSupportedAttributes(): ?array
    {
        return ['data-text-size'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        return in_array($value, self::VALUES, true) ? $value : null;
    }
}
