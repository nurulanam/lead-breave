<?php

namespace App\Services\Website;

/**
 * Looks up a hostname's addresses. Swapped for a fake in tests, so they never
 * depend on real DNS.
 */
class HostResolver
{
    /**
     * @return list<string> IPv4 and IPv6 addresses; empty when the name does not resolve.
     */
    public function resolve(string $host): array
    {
        $ips = gethostbynamel($host) ?: [];
        $v6 = @dns_get_record($host, DNS_AAAA);

        foreach (is_array($v6) ? $v6 : [] as $record) {
            if (! empty($record['ipv6'])) {
                $ips[] = (string) $record['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }
}
