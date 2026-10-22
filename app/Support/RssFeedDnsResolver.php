<?php

namespace App\Support;

class RssFeedDnsResolver
{
    /** @return list<string> */
    public function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        return collect($records ?: [])
            ->map(fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
