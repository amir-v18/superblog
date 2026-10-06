<?php

declare(strict_types=1);

namespace SuperblogEngine\Rss;

final class FeedFetcher
{
    /**
     * @return list<FeedItem>|\WP_Error
     */
    public function fetch(string $feedUrl, int $limit = 10): array|\WP_Error
    {
        $feed = fetch_feed($feedUrl);

        if (is_wp_error($feed)) {
            return $feed;
        }

        $limit = max(1, min($limit, 50));
        $items = $feed->get_items(0, $feed->get_item_quantity($limit));
        $feedItems = [];

        foreach ($items as $item) {
            $title = $item->get_title();
            $link = esc_url_raw($item->get_permalink() ?? '');

            if (!is_string($title) || trim($title) === '' || $link === '') {
                continue;
            }

            $description = $item->get_description();

            $feedItems[] = new FeedItem(
                title: wp_strip_all_tags($title),
                link: $link,
                publishedAt: $this->parseDate($item->get_date('c')),
                summary: is_string($description)
                    ? wp_strip_all_tags($description)
                    : null,
            );
        }

        return $feedItems;
    }

    private function parseDate(?string $date): ?\DateTimeImmutable
    {
        if ($date === null || trim($date) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($date);
        } catch (\Exception) {
            return null;
        }
    }
}