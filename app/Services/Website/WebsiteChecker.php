<?php

namespace App\Services\Website;

use App\Enums\DataSource;
use App\Enums\SocialPlatform;
use App\Enums\WebsiteCheckType;
use App\Models\Lead;
use App\Models\LeadEmail;
use App\Models\WebsiteCheck;
use App\Support\Domain;

/**
 * Runs the requested checks on a lead's website. The home page is fetched
 * once and shared; each check type writes its own row, so re-running one
 * leaves the others as they were.
 */
class WebsiteChecker
{
    private const STORED_EMAILS = 10;

    public function __construct(
        private WebsiteCrawler $crawler,
        private EmailExtractor $emails,
        private SocialExtractor $socials,
        private TechDetector $tech,
        private SeoAnalyzer $seo,
        private ParkedPageDetector $parked,
        private BotProtectionDetector $botProtection,
    ) {}

    /**
     * @param  list<WebsiteCheckType>  $types
     */
    public function check(Lead $lead, array $types): void
    {
        if (blank($lead->website)) {
            $this->failAll($lead, $types, 'This lead has no website.', ['reachable' => false, 'no_website' => true]);

            return;
        }

        try {
            $home = $this->crawler->home((string) $lead->website);
        } catch (DomainNotFound $e) {
            $this->failAll($lead, $types, $e->getMessage(), ['reachable' => false, 'dead' => true]);

            return;
        } catch (BlockedUrl|FetchFailed $e) {
            $this->failAll($lead, $types, $e->getMessage(), ['reachable' => false]);

            return;
        }

        $blockedBy = $this->botProtection->provider($home);
        $parked = $home->ok() ? $this->parked->reason($home) : null;
        $analysable = $home->ok() && $home->isHtml() && $parked === null;
        $unanalysable = match (true) {
            $blockedBy !== null => "{$blockedBy} bot protection blocks automated visits. The site works in a browser: open it there and add the details by hand.",
            $parked !== null => $parked,
            ! $home->ok() => "The home page returned an error (HTTP {$home->status}).",
            default => 'The home page is not a web page.',
        };

        foreach ($types as $type) {
            match ($type) {
                WebsiteCheckType::Crawl => $this->crawl($lead, $home, $parked, $blockedBy),
                WebsiteCheckType::Ssl => $this->record($lead, $type, WebsiteCheck::STATUS_OK, $this->ssl((string) $lead->website, $home)),
                WebsiteCheckType::Seo => $analysable
                    ? $this->record($lead, $type, WebsiteCheck::STATUS_OK, $this->seo->analyze($home))
                    : $this->record($lead, $type, WebsiteCheck::STATUS_FAILED, null, $unanalysable),
                WebsiteCheckType::Tech => $analysable
                    ? $this->record($lead, $type, WebsiteCheck::STATUS_OK, $this->tech->detect($home))
                    : $this->record($lead, $type, WebsiteCheck::STATUS_FAILED, null, $unanalysable),
            };
        }
    }

    private function crawl(Lead $lead, FetchResult $home, ?string $parked, ?string $blockedBy): void
    {
        $pages = [new CrawledPage('home', $home)];

        if ($parked === null && $blockedBy === null) {
            $pages = [...$pages, ...$this->crawler->contactPages($home)];
        }

        $siteDomain = (string) Domain::fromUrl($home->finalUrl);
        $html = [];
        $sets = [];

        foreach ($pages as $page) {
            if ($parked === null && $page->result->ok() && $page->result->isHtml()) {
                $html[] = $page->result->body;
                $sets[] = $this->emails->extract($page->result->body, $siteDomain, $page->kind, $page->result->finalUrl);
            }
        }

        $candidates = $this->emails->merge($sets);
        $socials = $this->socials->extract($html);

        $this->saveEmails($lead, $candidates);
        $this->saveSocials($lead, $socials);

        $this->record($lead, WebsiteCheckType::Crawl, WebsiteCheck::STATUS_OK, [
            'reachable' => true,
            'status' => $home->status,
            'final_url' => $home->finalUrl,
            'redirects' => $home->redirects,
            'seconds' => $home->seconds,
            'parked' => $parked,
            'blocked_by' => $blockedBy,
            'pages' => array_map(fn (CrawledPage $page): array => [
                'kind' => $page->kind,
                'url' => $page->result->finalUrl,
                'status' => $page->result->status,
            ], $pages),
            'emails_found' => count($candidates),
            'emails' => array_map(fn (EmailCandidate $candidate): array => [
                'email' => $candidate->email,
                'confidence' => $candidate->confidence,
                'source' => $candidate->source,
            ], array_slice($candidates, 0, self::STORED_EMAILS)),
            'socials_found' => count($socials),
            'socials' => array_map(fn (string $url, SocialPlatform $platform): array => ['platform' => $platform->value, 'url' => $url], array_keys($socials), $socials),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function ssl(string $website, FetchResult $home): array
    {
        return [
            'https' => $home->isHttps(),
            'certificate_valid' => $home->isHttps() && ! $home->sslBroken,
            'certificate_error' => $home->sslError,
            'upgraded_to_https' => ! str_starts_with(strtolower($website), 'https://') && $home->isHttps(),
        ];
    }

    /**
     * Add or refresh addresses found on the site. Addresses entered by hand
     * or imported are never changed.
     *
     * @param  list<EmailCandidate>  $candidates  best first
     */
    private function saveEmails(Lead $lead, array $candidates): void
    {
        if ($candidates === []) {
            return;
        }

        $existing = $lead->emails()->get()->keyBy('email');
        $saved = [];

        foreach ($candidates as $candidate) {
            /** @var LeadEmail|null $email */
            $email = $existing->get($candidate->email);

            if ($email !== null && $email->source !== DataSource::Crawl) {
                continue;
            }

            $email ??= new LeadEmail(['lead_id' => $lead->id, 'email' => $candidate->email, 'source' => DataSource::Crawl]);
            $email->fill(['confidence' => $candidate->confidence, 'is_role' => $candidate->isRole, 'found_on_url' => $candidate->foundOnUrl])->save();
            $saved[] = $email;
        }

        $best = $saved[0] ?? null;
        $primary = $lead->emails()->where('is_primary', true)->first();

        if ($best !== null && ($primary === null || ($primary->source === DataSource::Crawl && $best->confidence > $primary->confidence))) {
            $best->makePrimary();
        }
    }

    /**
     * @param  array<string, SocialPlatform>  $socials
     */
    private function saveSocials(Lead $lead, array $socials): void
    {
        $existing = $lead->socials()->pluck('url')->all();

        foreach ($socials as $url => $platform) {
            if (! in_array($url, $existing, true)) {
                $lead->socials()->create(['platform' => $platform, 'url' => $url, 'source' => DataSource::Crawl]);
            }
        }
    }

    /**
     * @param  list<WebsiteCheckType>  $types
     * @param  array<string, mixed>  $result
     */
    private function failAll(Lead $lead, array $types, string $reason, array $result): void
    {
        foreach ($types as $type) {
            $this->record($lead, $type, WebsiteCheck::STATUS_FAILED, $type === WebsiteCheckType::Crawl ? $result : null, $reason);
        }
    }

    /**
     * @param  array<string, mixed>|null  $result
     */
    private function record(Lead $lead, WebsiteCheckType $type, string $status, ?array $result, ?string $error = null): void
    {
        WebsiteCheck::query()->updateOrCreate(
            ['lead_id' => $lead->id, 'type' => $type],
            ['status' => $status, 'result' => $result, 'error' => $error, 'checked_at' => now()],
        );
    }
}
