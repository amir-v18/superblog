<?php

namespace App\Support;

use App\Enums\ArticleContentFormat;
use App\Models\Article;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;

/**
 * Validates and serializes the versioned article body contract.
 *
 * V1 keeps the title/H1 outside the document. Blocks and list items have stable UUIDs.
 * Inline runs contain plain text (never HTML), formatting marks, an approved size, and
 * optionally a safe link. Image blocks reference the article's Media row; alt text stays
 * on that Media record, while the caption belongs to the block/document.
 */
class ArticleContentDocument
{
    public const VERSION = 1;

    public const MAX_BYTES = 2_097_152;

    private const MAX_BLOCKS = 500;

    private const MAX_RUNS_PER_BLOCK = 2_000;

    private const MAX_LIST_ITEMS = 500;

    private const MAX_TEXT_LENGTH = 100_000;

    private const ALLOWED_SIZES = ['small', 'normal', 'large'];

    private const ALLOWED_MARKS = ['bold', 'italic'];

    /** @return array{version: int, blocks: list<array<string, mixed>>} canonical validated document */
    public function validate(mixed $document): array
    {
        $errors = [];
        $ids = [];

        if (! is_array($document) || ! $this->hasExactKeys($document, ['version', 'blocks'])) {
            $this->fail('Dokumentet får bara innehålla version och block.');
        }

        if (($document['version'] ?? null) !== self::VERSION) {
            $errors[] = 'Endast version 1 av artikelns dokumentformat stöds.';
        }

        $blocks = $document['blocks'] ?? null;
        if (! is_array($blocks) || ! array_is_list($blocks) || $blocks === []) {
            $errors[] = 'Dokumentet måste innehålla en ordnad lista med artikelblock.';
            $this->fail(...$errors);
        }

        if (count($blocks) > self::MAX_BLOCKS) {
            $errors[] = 'En artikel får innehålla högst 500 block.';
        }

        $introductionCount = 0;
        $hasH2 = false;
        $imageCount = 0;
        $blockTypeCounts = ['heading' => 0, 'list' => 0, 'paragraph' => 0];
        $canonicalBlocks = [];

        foreach ($blocks as $index => $block) {
            $path = "blocks.{$index}";

            if (! is_array($block) || ! isset($block['type']) || ! is_string($block['type'])) {
                $errors[] = "{$path} måste vara ett artikelblock av en typ som stöds.";

                continue;
            }

            $type = $block['type'];
            $allowedKeys = match ($type) {
                'introduction', 'paragraph' => ['id', 'type', 'runs'],
                'heading' => ['id', 'type', 'level', 'runs'],
                'list' => ['id', 'type', 'style', 'items'],
                'image' => ['id', 'type', 'media_id', 'caption'],
                default => null,
            };

            if ($allowedKeys === null) {
                $errors[] = "{$path}.type stöds inte.";

                continue;
            }

            if (! $this->hasExactKeys($block, $allowedKeys)) {
                $errors[] = "{$path} saknar obligatoriska uppgifter eller innehåller uppgifter som inte stöds.";
            }

            $id = $block['id'] ?? null;
            if (! $this->registerUuid($id, $ids)) {
                $errors[] = "{$path}.id måste vara ett unikt UUID.";
            }

            $canonical = ['id' => is_string($id) ? strtolower($id) : '', 'type' => $type];

            if ($type === 'introduction') {
                $introductionCount++;
                if ($index !== 0) {
                    $errors[] = 'Inledningen måste vara det första blocket i artikeltexten.';
                }
            } elseif ($index === 0) {
                $errors[] = 'Det första blocket i artikeltexten måste vara inledningen.';
            }

            if (in_array($type, ['introduction', 'paragraph', 'heading'], true)) {
                if (isset($blockTypeCounts[$type])) {
                    $blockTypeCounts[$type]++;
                }
                $runs = $this->validateRuns($block['runs'] ?? null, "{$path}.runs", $errors);
                if ($type !== 'paragraph' && ! $this->runsHaveText($runs)) {
                    $position = $type === 'introduction' ? null : $blockTypeCounts[$type];
                    $errors[] = $this->emptyTextBlockMessage($type, $position);
                }

                $canonical['runs'] = $runs;
            }

            if ($type === 'heading') {
                $level = $block['level'] ?? null;
                if (! in_array($level, [2, 3], true)) {
                    $errors[] = "{$path}.level måste vara 2 eller 3.";
                } elseif ($level === 2) {
                    $hasH2 = true;
                } elseif (! $hasH2) {
                    $errors[] = 'Lägg till en H2-huvudrubrik innan du använder en H3-underrubrik.';
                }
                $canonical['level'] = $level;
            }

            if ($type === 'list') {
                $blockTypeCounts['list']++;
                $style = $block['style'] ?? null;
                if (! in_array($style, ['bullet', 'numbered'], true)) {
                    $errors[] = "{$path}.style måste vara punktlista eller numrerad lista.";
                }

                $items = $block['items'] ?? null;
                if (! is_array($items) || ! array_is_list($items) || $items === [] || count($items) > self::MAX_LIST_ITEMS) {
                    $errors[] = "{$path}.items måste vara en lista med 1–500 punkter.";
                    $items = [];
                }

                $canonicalItems = [];
                foreach ($items as $itemIndex => $item) {
                    $itemPath = "{$path}.items.{$itemIndex}";
                    if (! is_array($item) || ! $this->hasExactKeys($item, ['id', 'runs'])) {
                        $errors[] = "{$itemPath} saknar obligatoriska uppgifter eller innehåller uppgifter som inte stöds.";

                        continue;
                    }

                    $itemId = $item['id'] ?? null;
                    if (! $this->registerUuid($itemId, $ids)) {
                        $errors[] = "{$itemPath}.id måste vara ett unikt UUID.";
                    }

                    $runs = $this->validateRuns($item['runs'] ?? null, "{$itemPath}.runs", $errors);
                    if (! $this->runsHaveText($runs)) {
                        $errors[] = 'Punkt '.($itemIndex + 1)." i lista {$blockTypeCounts['list']} är tom. Skriv text i punkten innan du sparar.";
                    }

                    $canonicalItems[] = ['id' => is_string($itemId) ? strtolower($itemId) : '', 'runs' => $runs];
                }

                $canonical['style'] = $style;
                $canonical['items'] = $canonicalItems;
            }

            if ($type === 'image') {
                $imageCount++;
                $mediaId = $block['media_id'] ?? null;
                if (! is_int($mediaId) || $mediaId < 1) {
                    $errors[] = "{$path}.media_id måste vara ett positivt heltal.";
                }

                $caption = $block['caption'] ?? '';
                if (! is_string($caption) || mb_strlen($caption) > 500) {
                    $errors[] = "{$path}.caption måste vara text på högst 500 tecken.";
                }

                $canonical['media_id'] = $mediaId;
                $canonical['caption'] = $caption;
            }

            $canonicalBlocks[] = $canonical;
        }

        if ($introductionCount !== 1) {
            $errors[] = 'En artikel måste ha exakt ett inledande stycke.';
        }

        if ($imageCount > 1) {
            $errors[] = 'En artikel får innehålla högst ett bildblock.';
        }

        if ($errors !== []) {
            $this->fail(...$errors);
        }

        return ['version' => self::VERSION, 'blocks' => $canonicalBlocks];
    }

    public function serialize(mixed $document): string
    {
        $canonical = $this->validate($document);

        try {
            $json = json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            $this->fail('Artikelns dokument kunde inte formateras.');
        }

        if (strlen($json) > self::MAX_BYTES) {
            $this->fail('Artikelns innehåll får vara högst 2 MB.');
        }

        return $json;
    }

    public function storeOn(Article $article, mixed $document): void
    {
        $article->content = $this->serialize($document);
        $article->content_format = ArticleContentFormat::BlocksV1;
    }

    /** @return array{version: int, blocks: list<array<string, mixed>>}|null */
    public function readFrom(Article $article): ?array
    {
        if ($article->content_format !== ArticleContentFormat::BlocksV1) {
            return null;
        }

        return $this->deserialize($article->content);
    }

    /** @return array{version: int, blocks: list<array<string, mixed>>} */
    public function deserialize(string $json): array
    {
        if (strlen($json) > self::MAX_BYTES) {
            $this->fail('Artikelns innehåll får vara högst 2 MB.');
        }

        try {
            $document = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->fail('Artikelns sparade dokument har ett ogiltigt format.');
        }

        return $this->validate($document);
    }

    /** @param array<string, true> $ids */
    private function registerUuid(mixed $id, array &$ids): bool
    {
        if (! is_string($id) || ! Str::isUuid($id)) {
            return false;
        }

        $normalized = strtolower($id);
        if (isset($ids[$normalized])) {
            return false;
        }

        $ids[$normalized] = true;

        return true;
    }

    /** @param list<string> $errors
     * @return list<array{text: string, marks: list<string>, size: string, href?: string}>
     */
    private function validateRuns(mixed $runs, string $path, array &$errors): array
    {
        if (! is_array($runs) || ! array_is_list($runs) || count($runs) > self::MAX_RUNS_PER_BLOCK) {
            $errors[] = "{$path} måste vara en ordnad lista med textdelar.";

            return [];
        }

        $canonicalRuns = [];
        foreach ($runs as $index => $run) {
            $runPath = "{$path}.{$index}";
            if (! is_array($run) || ! $this->hasAllowedKeys($run, ['text', 'marks', 'size', 'href']) || ! array_key_exists('text', $run)) {
                $errors[] = "{$runPath} saknar obligatoriska uppgifter eller innehåller uppgifter som inte stöds.";

                continue;
            }

            $text = $run['text'];
            if (! is_string($text) || mb_strlen($text) > self::MAX_TEXT_LENGTH) {
                $errors[] = "{$runPath}.text måste vara text på högst 100 000 tecken.";

                continue;
            }

            $marks = $run['marks'] ?? [];
            if (! is_array($marks) || ! array_is_list($marks)
                || count(array_filter($marks, 'is_string')) !== count($marks)
                || array_diff($marks, self::ALLOWED_MARKS) !== []
                || count(array_unique($marks)) !== count($marks)) {
                $errors[] = "{$runPath}.marks får innehålla fetstil och kursiv stil, högst en gång vardera.";
                $marks = [];
            }

            $size = $run['size'] ?? 'normal';
            if (! in_array($size, self::ALLOWED_SIZES, true)) {
                $errors[] = "{$runPath}.size måste vara liten, normal eller stor.";
                $size = 'normal';
            }

            $canonical = ['text' => $text, 'marks' => array_values($marks), 'size' => $size];

            if (array_key_exists('href', $run)) {
                if (! is_string($run['href']) || ! $this->isSafeHref($run['href'])) {
                    $errors[] = "{$runPath}.href måste vara en säker HTTP-, HTTPS-, mailto- eller relativ webbadress.";
                } else {
                    $canonical['href'] = trim($run['href']);
                }
            }

            $canonicalRuns[] = $canonical;
        }

        return $canonicalRuns;
    }

    private function isSafeHref(string $href): bool
    {
        $href = trim($href);
        if ($href === '' || preg_match('/[\x00-\x20]/', $href) === 1 || str_contains($href, '\\')) {
            return false;
        }

        $scheme = parse_url($href, PHP_URL_SCHEME);
        if ($scheme !== null) {
            $scheme = strtolower($scheme);
            if (! in_array($scheme, ['http', 'https', 'mailto'], true)) {
                return false;
            }

            if (in_array($scheme, ['http', 'https'], true)) {
                return filter_var($href, FILTER_VALIDATE_URL) !== false;
            }

            return filter_var(substr($href, strlen('mailto:')), FILTER_VALIDATE_EMAIL) !== false;
        }

        return ! str_starts_with($href, '//');
    }

    private function runsHaveText(array $runs): bool
    {
        foreach ($runs as $run) {
            if (trim($run['text']) !== '') {
                return true;
            }
        }

        return false;
    }

    private function emptyTextBlockMessage(string $type, ?int $position): string
    {
        return match ($type) {
            'introduction' => 'Inledningen är tom. Skriv en introduktion innan du sparar.',
            'heading' => "Rubrik {$position} är tom. Skriv text i rubriken innan du sparar.",
            default => "Stycke {$position} är tomt. Skriv text i stycket innan du sparar.",
        };
    }

    private function hasExactKeys(array $value, array $keys): bool
    {
        $actual = array_keys($value);
        sort($actual);
        sort($keys);

        return $actual === $keys;
    }

    private function hasAllowedKeys(array $value, array $allowed): bool
    {
        return array_diff(array_keys($value), $allowed) === [];
    }

    private function fail(string ...$messages): never
    {
        throw ValidationException::withMessages(['document' => $messages]);
    }
}
