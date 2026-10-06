<?php

declare(strict_types=1);

namespace SuperblogEngine\Rss;

final class FeedItem
{
    public function __construct(
        public readonly string $title,
        public readonly string $link,
        public readonly ?\DateTimeImmutable $publishedAt = null,
        public readonly ?string $summary = null,
    ) {
    }
}
