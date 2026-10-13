<?php

namespace App\Services\Website;

use App\Enums\WebsiteCheckType;
use App\Models\WebsiteCheck;

/**
 * Turns a stored check into lines a person can read: what was checked, how it
 * went (pass, warn, fail or info), and the value behind it.
 */
class WebsiteFindings
{
    /**
     * @return list<array{label: string, state: 'pass'|'warn'|'fail'|'info', value: string|null}>
     */
    public static function for(WebsiteCheck $check): array
    {
        if ($check->failed()) {
            return [['label' => (string) $check->error, 'state' => 'fail', 'value' => null]];
        }

        $r = $check->result ?? [];

        return array_values(array_filter(match ($check->type) {
            WebsiteCheckType::Crawl => self::crawl($r),
            WebsiteCheckType::Ssl => self::ssl($r),
            WebsiteCheckType::Seo => self::seo($r),
            WebsiteCheckType::Tech => self::tech($r),
        }));
    }

    /**
     * One line under the card's title.
     */
    public static function summary(WebsiteCheck $check): string
    {
        if ($check->failed()) {
            return 'Could not complete';
        }

        $findings = self::for($check);
        $fails = count(array_filter($findings, fn (array $finding): bool => $finding['state'] === 'fail'));
        $warns = count(array_filter($findings, fn (array $finding): bool => $finding['state'] === 'warn'));

        return match (true) {
            $fails > 0 => $fails.' '.str('problem')->plural($fails).($warns > 0 ? ", {$warns} to improve" : ''),
            $warns > 0 => $warns.' '.str('thing')->plural($warns).' to improve',
            default => 'All good',
        };
    }

    /**
     * @param  array<string, mixed>  $r
     * @return list<array{label: string, state: 'pass'|'warn'|'fail'|'info', value: string|null}|null>
     */
    private static function crawl(array $r): array
    {
        $status = (int) ($r['status'] ?? 0);
        $seconds = (float) ($r['seconds'] ?? 0);

        return [
            match (true) {
                filled($r['blocked_by'] ?? null) => self::line('Blocks automated checks ('.$r['blocked_by'].')', 'warn', 'Works in a browser; add details by hand'),
                filled($r['parked'] ?? null) => self::line((string) $r['parked'], 'fail'),
                default => self::line('Home page loads', $status >= 200 && $status < 300 ? 'pass' : 'fail', "HTTP {$status}"),
            },
            self::line('Response time', $seconds > 3 ? 'warn' : 'pass', number_format($seconds, 2).' s'),
            ($r['redirects'] ?? 0) > 0 ? self::line('Redirects', 'info', $r['redirects'].' to '.($r['final_url'] ?? '')) : null,
            self::line('Pages read', 'info', (string) count((array) ($r['pages'] ?? []))),
            self::line('Email addresses found', ($r['emails_found'] ?? 0) > 0 ? 'pass' : 'warn', (string) ($r['emails_found'] ?? 0)),
            self::line('Social profiles found', ($r['socials_found'] ?? 0) > 0 ? 'pass' : 'info', (string) ($r['socials_found'] ?? 0)),
        ];
    }

    /**
     * @param  array<string, mixed>  $r
     * @return list<array{label: string, state: 'pass'|'warn'|'fail'|'info', value: string|null}|null>
     */
    private static function ssl(array $r): array
    {
        return [
            self::line('Uses HTTPS', ($r['https'] ?? false) ? 'pass' : 'fail', ($r['https'] ?? false) ? 'Yes' : 'No, browsers show "Not secure"'),
            ($r['https'] ?? false) ? self::line('Certificate trusted', ($r['certificate_valid'] ?? false) ? 'pass' : 'fail', $r['certificate_error'] ?? 'Valid') : null,
            ($r['upgraded_to_https'] ?? false) ? self::line('Plain http address redirects to https', 'pass', null) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $r
     * @return list<array{label: string, state: 'pass'|'warn'|'fail'|'info', value: string|null}|null>
     */
    private static function seo(array $r): array
    {
        $titleLength = (int) ($r['title_length'] ?? 0);
        $descriptionLength = (int) ($r['description_length'] ?? 0);
        $h1 = (int) ($r['h1_count'] ?? 0);
        $year = $r['copyright_year'] ?? null;
        $currentYear = (int) now()->format('Y');

        return [
            self::line('Page title', match (true) {
                $titleLength === 0 => 'fail',
                $titleLength < 20, $titleLength > 65 => 'warn',
                default => 'pass',
            }, $titleLength === 0 ? 'Missing' : '"'.str((string) $r['title'])->limit(60).'" ('.$titleLength.' characters)'),
            self::line('Meta description', $descriptionLength === 0 ? 'fail' : ($descriptionLength < 70 || $descriptionLength > 160 ? 'warn' : 'pass'), $descriptionLength === 0 ? 'Missing' : $descriptionLength.' characters'),
            self::line('Main heading (H1)', $h1 === 1 ? 'pass' : ($h1 === 0 ? 'fail' : 'warn'), $h1 === 0 ? 'Missing' : ($h1 === 1 ? 'One' : "{$h1} on one page")),
            self::line('Works on phones (viewport)', ($r['has_viewport'] ?? false) ? 'pass' : 'fail', null),
            self::line('Image descriptions (alt text)', ($r['alt_coverage'] ?? 100) >= 80 ? 'pass' : 'warn', ($r['alt_coverage'] ?? 100).'% of '.($r['image_count'] ?? 0).' images'),
            self::line('Business details for Google (schema)', ($r['has_schema'] ?? false) ? 'pass' : 'warn', null),
            self::line('Canonical link', ($r['has_canonical'] ?? false) ? 'pass' : 'warn', null),
            self::line('Social sharing preview (Open Graph)', ($r['has_open_graph'] ?? false) ? 'pass' : 'warn', null),
            self::line('Favicon', ($r['has_favicon'] ?? false) ? 'pass' : 'warn', null),
            ($r['mixed_content'] ?? false) ? self::line('Loads insecure (http) files on an https page', 'fail', null) : null,
            $year !== null ? self::line('Copyright year in footer', $year < $currentYear - 1 ? 'warn' : 'pass', (string) $year) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $r
     * @return list<array{label: string, state: 'pass'|'warn'|'fail'|'info', value: string|null}|null>
     */
    private static function tech(array $r): array
    {
        $lines = [
            self::line('Platform', 'info', trim(($r['cms'] ?? 'Not recognised').' '.($r['cms_version'] ?? ''))),
            ($r['builder'] ?? null) ? self::line('Page builder', 'info', (string) $r['builder']) : null,
            ($r['server'] ?? null) ? self::line('Server', 'info', (string) $r['server']) : null,
            ($r['libraries'] ?? []) !== [] ? self::line('Tools found', 'info', implode(', ', (array) $r['libraries'])) : null,
        ];

        foreach ((array) ($r['dated_markers'] ?? []) as $marker) {
            $lines[] = self::line('Dated technology', 'warn', (string) $marker);
        }

        if (($r['dated_markers'] ?? []) === []) {
            $lines[] = self::line('No dated technology found', 'pass', null);
        }

        return $lines;
    }

    /**
     * @param  'pass'|'warn'|'fail'|'info'  $state
     * @return array{label: string, state: 'pass'|'warn'|'fail'|'info', value: string|null}
     */
    private static function line(string $label, string $state, ?string $value = null): array
    {
        return ['label' => $label, 'state' => $state, 'value' => $value];
    }
}
