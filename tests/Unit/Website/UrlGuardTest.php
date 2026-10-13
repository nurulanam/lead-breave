<?php

use App\Services\Website\UrlGuard;

it('treats only public addresses as public', function (string $ip, bool $public) {
    expect(UrlGuard::isPublicIp($ip))->toBe($public);
})->with([
    'public v4' => ['93.184.216.34', true],
    'private 10/8' => ['10.0.0.5', false],
    'private 192.168' => ['192.168.1.10', false],
    'private 172.16/12' => ['172.20.0.1', false],
    'loopback' => ['127.0.0.1', false],
    'cloud metadata' => ['169.254.169.254', false],
    'carrier-grade NAT' => ['100.64.1.1', false],
    'public v6' => ['2606:2800:220:1:248:1893:25c8:1946', true],
    'v6 loopback' => ['::1', false],
    'v6 unique local' => ['fd00::1', false],
    'v6 link-local' => ['fe80::1', false],
    'v4-mapped private' => ['::ffff:10.0.0.1', false],
    'not an ip' => ['example.com', false],
]);
