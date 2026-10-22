<?php

namespace App\Services;

use App\Models\RssSource;
use App\Support\RssFeedDnsResolver;
use App\Support\RssFeedFetchException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use SimpleXMLElement;
use Throwable;

class RssFeedFetcher
{
    private const MAX_RESPONSE_BYTES = 2_097_152;

    private const MAX_ITEMS = 500;

    public function __construct(private readonly RssFeedDnsResolver $dnsResolver) {}

    public function fetch(RssSource $rssSource): int
    {
        $target = $this->safeTarget($rssSource->url);
        $bytes = 0;
        $sizeExceeded = false;

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/atom+xml, application/rss+xml, application/xml, text/xml',
                'Accept-Encoding' => 'identity',
                'User-Agent' => 'Superblog RSS reader',
            ])->withOptions([
                'allow_redirects' => false,
                'connect_timeout' => 5,
                'timeout' => 15,
                'decode_content' => false,
                // An empty proxy value disables environment-configured proxies so the checked IP is the actual peer.
                'proxy' => '',
                'curl' => $target['resolve'] === null ? [] : [CURLOPT_RESOLVE => [$target['resolve']]],
                'progress' => function (int $downloadTotal, int $downloaded) use (&$bytes, &$sizeExceeded): bool {
                    $bytes = $downloaded;
                    $sizeExceeded = $downloaded > self::MAX_RESPONSE_BYTES || $downloadTotal > self::MAX_RESPONSE_BYTES;

                    return $sizeExceeded;
                },
                'on_headers' => function ($response): void {
                    $length = (int) $response->getHeaderLine('Content-Length');

                    if ($length > self::MAX_RESPONSE_BYTES) {
                        throw new RssFeedFetchException('The feed is larger than the allowed 2 MB limit.');
                    }
                },
            ])->get($rssSource->url);
        } catch (Throwable $exception) {
            if ($exception instanceof RssFeedFetchException) {
                throw $exception;
            }

            if ($sizeExceeded || $bytes > self::MAX_RESPONSE_BYTES) {
                throw new RssFeedFetchException('The feed is larger than the allowed 2 MB limit.');
            }

            throw new RssFeedFetchException('The feed could not be reached. Check its URL and try again.');
        }

        if (! $response->successful()) {
            throw new RssFeedFetchException('The feed server returned an error response.');
        }

        if ((int) $response->header('Content-Length') > self::MAX_RESPONSE_BYTES) {
            throw new RssFeedFetchException('The feed is larger than the allowed 2 MB limit.');
        }

        $body = $response->body();

        if (strlen($body) > self::MAX_RESPONSE_BYTES) {
            throw new RssFeedFetchException('The feed is larger than the allowed 2 MB limit.');
        }

        $items = $this->parse($body);

        return DB::transaction(function () use ($rssSource, $items): int {
            $lockedSource = RssSource::whereKey($rssSource->getKey())->lockForUpdate()->firstOrFail();
            $imported = 0;

            foreach ($items as $item) {
                $identity = $item['guid'] ?: ($item['url'] ?: hash('sha256', $item['title'].'|'.$item['published_at'].'|'.$item['content']));
                $externalId = hash('sha256', $identity);

                $alreadyImported = $lockedSource->rssItems()->where(function ($query) use ($externalId, $item): void {
                    $query->where('external_id', $externalId);

                    if ($item['guid'] !== null) {
                        $query->orWhere('guid', $item['guid']);
                    } elseif ($item['url'] !== '') {
                        $query->orWhere('url', $item['url']);
                    }
                })->exists();

                if ($alreadyImported) {
                    continue;
                }

                $lockedSource->rssItems()->create([
                    'external_id' => $externalId,
                    'guid' => $item['guid'],
                    'title' => $item['title'],
                    'url' => $item['url'],
                    'author' => $item['author'],
                    'published_at' => $item['published_at'],
                    'content' => $item['content'],
                    'summary' => $item['summary'],
                    'content_hash' => hash('sha256', $item['content'] ?? $item['summary'] ?? ''),
                ]);

                $imported++;
            }

            $lockedSource->forceFill(['last_fetched_at' => now()])->save();

            return $imported;
        });
    }

    /** @return array{resolve: ?string} */
    private function safeTarget(string $url): array
    {
        $parts = parse_url($url);

        if (! is_array($parts)) {
            throw new RssFeedFetchException('This feed URL is not valid.');
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower(rtrim(trim($parts['host'] ?? '', '[]'), '.'));
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        if (! in_array($scheme, ['http', 'https'], true)
            || $host === ''
            || isset($parts['user'])
            || isset($parts['pass'])
            || ! in_array($port, [80, 443], true)
            || ($scheme === 'http' && $port !== 80)
            || ($scheme === 'https' && $port !== 443)
            || $host === 'localhost'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')) {
            throw new RssFeedFetchException('This feed URL is not allowed. Use a public HTTP or HTTPS feed URL.');
        }

        $isIp = filter_var($host, FILTER_VALIDATE_IP) !== false;
        $addresses = $isIp ? [$host] : $this->dnsResolver->resolve($host);

        if ($addresses === []) {
            throw new RssFeedFetchException('The feed hostname could not be resolved.');
        }

        foreach ($addresses as $address) {
            if (! $this->isPublicAddress($address)) {
                throw new RssFeedFetchException('This feed URL resolves to a private or reserved network address.');
            }
        }

        if ($isIp) {
            return ['resolve' => null];
        }

        $address = $addresses[0];
        $curlAddress = str_contains($address, ':') ? '['.$address.']' : $address;

        return ['resolve' => $host.':'.$port.':'.$curlAddress];
    }

    private function isPublicAddress(string $address): bool
    {
        $packed = @inet_pton($address);

        if ($packed === false) {
            return false;
        }

        if (strlen($packed) === 16) {
            // Only global-unicast IPv6 is allowed; this also rejects mapped IPv4 and local ranges.
            if ((ord($packed[0]) & 0xE0) !== 0x20) {
                return false;
            }

            return ! $this->matchesAnyCidr($packed, ['2001::/23', '2001:db8::/32', '2002::/16']);
        }

        return ! $this->matchesAnyCidr($packed, [
            '0.0.0.0/8',
            '10.0.0.0/8',
            '100.64.0.0/10',
            '127.0.0.0/8',
            '169.254.0.0/16',
            '172.16.0.0/12',
            '192.0.0.0/24',
            '192.0.2.0/24',
            '192.88.99.0/24',
            '192.168.0.0/16',
            '198.18.0.0/15',
            '198.51.100.0/24',
            '203.0.113.0/24',
            '224.0.0.0/4',
            '240.0.0.0/4',
        ]);
    }

    /** @param list<string> $ranges */
    private function matchesAnyCidr(string $address, array $ranges): bool
    {
        foreach ($ranges as $range) {
            [$network, $prefix] = explode('/', $range);
            $packedNetwork = inet_pton($network);
            $prefix = (int) $prefix;
            $wholeBytes = intdiv($prefix, 8);
            $remainingBits = $prefix % 8;

            if (substr($address, 0, $wholeBytes) !== substr($packedNetwork, 0, $wholeBytes)) {
                continue;
            }

            if ($remainingBits === 0) {
                return true;
            }

            $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

            if ((ord($address[$wholeBytes]) & $mask) === (ord($packedNetwork[$wholeBytes]) & $mask)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array{guid: ?string, title: string, url: string, author: ?string, published_at: ?CarbonImmutable, content: ?string, summary: ?string}> */
    private function parse(string $body): array
    {
        if (trim($body) === '' || preg_match('/<!DOCTYPE/i', $body)) {
            throw new RssFeedFetchException('The feed is empty or contains unsupported XML.');
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA | LIBXML_COMPACT);

            if ($xml === false) {
                throw new RssFeedFetchException('The feed contains malformed XML.');
            }

            if (! in_array($xml->getName(), ['rss', 'feed', 'RDF'], true)) {
                throw new RssFeedFetchException('The response is not a supported RSS or Atom feed.');
            }

            $entries = $xml->channel->item ?? $xml->entry ?? $xml->item ?? [];
            $items = [];

            foreach ($entries as $entry) {
                if (count($items) >= self::MAX_ITEMS) {
                    break;
                }

                $namespaces = $entry->getNamespaces(true);
                $contentNamespace = $namespaces['content'] ?? 'http://purl.org/rss/1.0/modules/content/';
                $dcNamespace = $namespaces['dc'] ?? 'http://purl.org/dc/elements/1.1/';
                $encoded = (string) ($entry->children($contentNamespace)->encoded ?? '');
                $content = $encoded !== '' ? $encoded : (string) ($entry->content ?? $entry->description ?? '');
                $summary = (string) ($entry->summary ?? $entry->description ?? '');
                $link = (string) ($entry->link ?? '');

                if ($link === '' && isset($entry->link)) {
                    foreach ($entry->link as $candidate) {
                        if ((string) ($candidate['rel'] ?? 'alternate') === 'alternate' && isset($candidate['href'])) {
                            $link = (string) $candidate['href'];
                            break;
                        }
                    }
                }

                $published = (string) ($entry->pubDate ?? $entry->published ?? $entry->updated ?? $entry->children($dcNamespace)->date ?? '');
                $publishedAt = null;

                if ($published !== '') {
                    try {
                        $publishedAt = CarbonImmutable::parse($published);
                    } catch (Throwable) {
                        // A malformed date should not prevent importing the rest of the feed.
                    }
                }

                $guid = trim((string) ($entry->guid ?? $entry->id ?? ''));
                $items[] = [
                    'guid' => $guid === '' ? null : Str::limit($guid, 255, ''),
                    'title' => Str::limit(trim((string) ($entry->title ?? '')) ?: '(Untitled item)', 255, ''),
                    'url' => $this->limitTextBytes(trim($link)),
                    'author' => Str::limit(trim((string) ($entry->author->name ?? $entry->author ?? '')) ?: '', 255, '') ?: null,
                    'published_at' => $publishedAt,
                    'content' => $content === '' ? null : $content,
                    'summary' => $summary === '' ? null : $this->limitTextBytes($summary),
                ];
            }

            return $items;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function limitTextBytes(string $value): string
    {
        return mb_strcut($value, 0, 65535, 'UTF-8');
    }
}
