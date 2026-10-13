<?php

namespace App\Services\Website;

/**
 * Recognises bot-protection challenge pages. These sites work in a browser
 * but refuse automated visits until a JavaScript challenge is solved, so the
 * check cannot read them and should say so rather than report an error.
 */
class BotProtectionDetector
{
    /**
     * The protection service in the way, or null when the response is a normal page.
     */
    public function provider(FetchResult $page): ?string
    {
        if (! in_array($page->status, [202, 403, 429, 503], true)) {
            return null;
        }

        $body = mb_strtolower(substr($page->body, 0, 20000));
        $server = mb_strtolower($page->header('server'));

        return match (true) {
            $page->header('cf-mitigated') !== '',
            str_contains($server, 'cloudflare') && (str_contains($body, 'just a moment') || str_contains($body, 'challenge-platform') || str_contains($body, 'attention required') || str_contains($body, 'cf-chl')) => 'Cloudflare',
            str_contains($body, 'sucuri website firewall') || $page->header('x-sucuri-id') !== '' => 'Sucuri',
            str_contains($body, 'incapsula incident') || str_contains($body, '_incapsula_resource') => 'Imperva',
            str_contains($server, 'akamaighost') && str_contains($body, 'access denied') => 'Akamai',
            str_contains($body, 'datadome') || $page->header('x-datadome') !== '' => 'DataDome',
            str_contains($body, 'px-captcha') || str_contains($body, 'perimeterx') => 'PerimeterX',
            default => null,
        };
    }
}
