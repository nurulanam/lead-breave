<?php

namespace App\Services\Website;

/**
 * Fingerprints the platform and front-end stack, and flags markers of a site
 * nobody has touched in years. Ported from LeadMap's Tech_Detector.
 */
class TechDetector
{
    /** @var array<string, list<string>> */
    private const CMS = [
        'WordPress' => ['#/wp-content/#i', '#/wp-includes/#i', '#wp-json#i'],
        'Shopify' => ['#cdn\.shopify\.com#i', '#Shopify\.theme#i'],
        'Wix' => ['#static\.wixstatic#i', '#wix-?code#i'],
        'Squarespace' => ['#static1\.squarespace#i', '#squarespace\.com#i'],
        'Weebly' => ['#weebly\.com#i', '#weeblycloud#i'],
        'Joomla' => ['#/media/jui/#i', '#option=com_#i'],
        'Drupal' => ['#/sites/default/files/#i', '#Drupal\.settings#i'],
        'GoDaddy' => ['#godaddysites\.com#i', '#/websitebuilder/#i'],
        'Duda' => ['#dudamobile#i', '#d\.dudaimg#i'],
        'Webflow' => ['#webflow#i'],
    ];

    /** @var array<string, string> */
    private const BUILDERS = [
        'Elementor' => '#elementor-#i',
        'Divi' => '#/themes/Divi/#i',
        'WPBakery' => '#js_composer#i',
        'Visual Composer' => '#vc_row#i',
        'Wix' => '#static\.wixstatic#i',
        'Squarespace' => '#static1\.squarespace#i',
        'GoDaddy' => '#godaddysites\.com#i',
        'Duda' => '#d\.dudaimg#i',
    ];

    /** @var array<string, string> */
    private const DATED = [
        'Flash content' => '#<(object|embed)[^>]+(shockwave-flash|\.swf)#i',
        'Table-based layout' => '#<table[^>]+(cellpadding|cellspacing|border=)#i',
        '<font> tags' => '#<font\b#i',
        'Marquee or blink text' => '#<(marquee|blink)\b#i',
        'Frames' => '#<frameset\b#i',
        'Bootstrap 2' => '#bootstrap[/.\-]2\.[0-9.]+#i',
        'Bootstrap 3' => '#bootstrap[/.\-]3\.[0-9.]+#i',
        'XHTML doctype' => '#<!DOCTYPE[^>]+XHTML#i',
        'Microsoft FrontPage' => '#content=["\']Microsoft FrontPage#i',
    ];

    /** @var array<string, string> */
    private const LIBRARIES = [
        'React' => '#(react(-dom)?[.\-][0-9]|__REACT_|data-reactroot)#i',
        'Vue' => '#(vue[.\-][0-9]|__vue__|data-v-[0-9a-f]{8})#i',
        'Tailwind' => '#tailwind#i',
        'jQuery' => '#jquery#i',
        'Google Analytics' => '#(googletagmanager\.com/gtag|google-analytics\.com)#i',
        'Google Tag Manager' => '#googletagmanager\.com/gtm#i',
        'Facebook Pixel' => '#connect\.facebook\.net/[^"\']+/fbevents#i',
    ];

    /**
     * @return array{cms: string|null, cms_version: string|null, builder: string|null, generator: string|null, server: string|null, jquery_version: string|null, libraries: list<string>, dated_markers: list<string>}
     */
    public function detect(FetchResult $page): array
    {
        $html = $page->body;
        $generator = preg_match('/<meta[^>]+name=["\']generator["\'][^>]+content=["\']([^"\']+)/i', $html, $match) ? trim($match[1]) : null;
        $cms = $this->firstMatch(self::CMS, $html) ?? $this->fromGenerator($generator);
        $dated = [];
        $cmsVersion = null;

        if ($cms === 'WordPress' && $generator !== null && preg_match('/WordPress\s+([0-9.]+)/i', $generator, $match)) {
            $cmsVersion = $match[1];

            if (version_compare($cmsVersion, '6.0', '<')) {
                $dated[] = "WordPress {$cmsVersion}";
            }
        }

        $jquery = preg_match('#jquery[.\-/]?(?:core)?[.\-]?([0-9]+\.[0-9]+(?:\.[0-9]+)?)(?:\.min)?\.js#i', $html, $match) ? $match[1] : null;

        if ($jquery !== null && version_compare($jquery, '2.0', '<')) {
            $dated[] = "jQuery {$jquery}";
        }

        foreach (self::DATED as $label => $pattern) {
            if (preg_match($pattern, $html)) {
                $dated[] = $label;
            }
        }

        $libraries = [];

        foreach (self::LIBRARIES as $label => $pattern) {
            if (preg_match($pattern, $html)) {
                $libraries[] = $label;
            }
        }

        $builder = null;

        foreach (self::BUILDERS as $label => $pattern) {
            if (preg_match($pattern, $html)) {
                $builder = $label;

                break;
            }
        }

        return [
            'cms' => $cms,
            'cms_version' => $cmsVersion,
            'builder' => $builder,
            'generator' => $generator,
            'server' => $page->header('server') ?: null,
            'jquery_version' => $jquery,
            'libraries' => $libraries,
            'dated_markers' => array_values(array_unique($dated)),
        ];
    }

    /**
     * @param  array<string, list<string>>  $signatures
     */
    private function firstMatch(array $signatures, string $html): ?string
    {
        foreach ($signatures as $name => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $html)) {
                    return $name;
                }
            }
        }

        return null;
    }

    private function fromGenerator(?string $generator): ?string
    {
        foreach (array_keys(self::CMS) as $name) {
            if ($generator !== null && stripos($generator, $name) !== false) {
                return $name;
            }
        }

        return null;
    }
}
