<?php

namespace App\Services\Website;

/**
 * Server-side request forgery guard. Only public http(s) websites on ports
 * 80 and 443 may be fetched; anything that points inside a network,
 * including through DNS, is refused. Ported from LeadMap's Url_Guard.
 */
class UrlGuard
{
    private const ALLOWED_SCHEMES = ['http', 'https'];

    private const ALLOWED_PORTS = [80, 443];

    private const BLOCKED_HOSTS = [
        'localhost', 'localhost.localdomain', 'ip6-localhost', 'ip6-loopback',
        'metadata', 'metadata.google.internal', 'instance-data',
    ];

    private const BLOCKED_SUFFIXES = [
        '.local', '.localhost', '.internal', '.intranet', '.private',
        '.corp', '.home', '.lan', '.test', '.example', '.invalid', '.onion',
    ];

    /** @var list<string> */
    private const BLOCKED_V4_CIDRS = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16',
        '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24', '192.168.0.0/16', '198.18.0.0/15',
        '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4',
    ];

    /** @var list<string> */
    private const BLOCKED_V6_PREFIXES = ['fc', 'fd', 'fe8', 'fe9', 'fea', 'feb', 'ff', '2001:db8', '64:ff9b'];

    private const HOSTNAME = '/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/';

    /** @var array<string, list<string>> */
    private array $dnsCache = [];

    public function __construct(private HostResolver $resolver) {}

    /**
     * @throws BlockedUrl
     */
    public function check(string $url): SafeUrl
    {
        $url = trim($url);

        if ($url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x1F\x7F]/', $url)) {
            throw new BlockedUrl('The address is empty, too long or malformed.');
        }

        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['host'])) {
            throw new BlockedUrl('The address could not be read.');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if (! in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            throw new BlockedUrl('Only http and https websites are checked.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new BlockedUrl('Addresses containing a username or password are not checked.');
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);

        if (! in_array($port, self::ALLOWED_PORTS, true)) {
            throw new BlockedUrl('Only ports 80 and 443 are checked.');
        }

        $host = strtolower(rtrim(trim((string) $parts['host'], '[]'), '.'));

        if (in_array($host, self::BLOCKED_HOSTS, true)) {
            throw new BlockedUrl('That is not a public website.');
        }

        foreach (self::BLOCKED_SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix)) {
                throw new BlockedUrl('That address is in a reserved namespace.');
            }
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolve($host);

        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) {
                throw new BlockedUrl('That address points to a private or reserved network.');
            }
        }

        return new SafeUrl($url, $host, $port, $ips[0]);
    }

    /**
     * Shape-only check, without DNS, for filtering links found on a page.
     */
    public function looksPublic(string $url): bool
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));

        return is_array($parts)
            && in_array(strtolower((string) ($parts['scheme'] ?? '')), self::ALLOWED_SCHEMES, true)
            && (preg_match(self::HOSTNAME, $host) === 1 || (filter_var($host, FILTER_VALIDATE_IP) && self::isPublicIp($host)));
    }

    public static function isPublicIp(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP) || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            foreach (self::BLOCKED_V4_CIDRS as $cidr) {
                [$subnet, $bits] = explode('/', $cidr);
                $mask = -1 << (32 - (int) $bits);

                if ((ip2long($ip) & $mask) === (ip2long($subnet) & $mask)) {
                    return false;
                }
            }

            return true;
        }

        $normalized = strtolower((string) inet_ntop((string) inet_pton($ip)));

        if (preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/', $normalized, $match)) {
            return self::isPublicIp($match[1]);
        }

        if ($normalized === '::1' || $normalized === '::') {
            return false;
        }

        foreach (self::BLOCKED_V6_PREFIXES as $prefix) {
            if (str_starts_with($normalized, $prefix)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return non-empty-list<string>
     *
     * @throws BlockedUrl
     */
    private function resolve(string $host): array
    {
        if (preg_match(self::HOSTNAME, $host) !== 1) {
            throw new BlockedUrl('That is not a valid website address.');
        }

        $ips = $this->dnsCache[$host] ??= $this->resolver->resolve($host);

        if ($ips === []) {
            throw new DomainNotFound('The domain does not exist or has no website (it does not resolve).');
        }

        return $ips;
    }
}
