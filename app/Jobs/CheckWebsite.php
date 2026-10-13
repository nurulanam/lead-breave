<?php

namespace App\Jobs;

use App\Enums\LeadStatus;
use App\Enums\WebsiteCheckType;
use App\Models\Lead;
use App\Services\Website\CheckProgress;
use App\Services\Website\WebsiteChecker;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Checks one lead's website. Unique per lead, and limited per domain so
 * several leads on one site never hammer it.
 */
class CheckWebsite implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 110;

    public int $uniqueFor = 600;

    /**
     * @param  list<string>  $types  WebsiteCheckType values; empty means every type.
     */
    public function __construct(public int $leadId, public array $types = [])
    {
        $this->onQueue('enrich');
    }

    /**
     * Queue a check for a lead that has a website.
     */
    public static function forLead(Lead $lead, WebsiteCheckType ...$types): void
    {
        if (filled($lead->website)) {
            CheckProgress::queued($lead->id);
            self::dispatch($lead->id, array_map(fn (WebsiteCheckType $type): string => $type->value, $types))->afterCommit();
        }
    }

    public function uniqueId(): string
    {
        return $this->leadId.':'.implode(',', $this->types);
    }

    public function handle(WebsiteChecker $checker): void
    {
        $lead = Lead::query()->find($this->leadId);

        if ($lead === null) {
            return;
        }

        $key = 'website-check:'.($lead->domain ?? $lead->id);

        if (RateLimiter::tooManyAttempts($key, (int) config('services.website_check.per_domain_per_minute'))) {
            CheckProgress::waiting($lead->id, 'This website was checked several times just now. Waiting a minute to be polite…');
            $this->release(RateLimiter::availableIn($key) + 1);

            return;
        }

        RateLimiter::hit($key, 60);

        $types = $this->types === []
            ? WebsiteCheckType::cases()
            : array_map(fn (string $type): WebsiteCheckType => WebsiteCheckType::from($type), $this->types);

        CheckProgress::step($lead->id, 5, 'Starting the check');
        $movedToEnriching = $this->startEnriching($lead);

        try {
            $checker->check($lead, $types, fn (int $percent, string $step) => CheckProgress::step($lead->id, $percent, $step));
        } catch (Throwable $e) {
            CheckProgress::failed($lead->id, 'The check stopped unexpectedly. It will be retried.');

            throw $e;
        } finally {
            if ($movedToEnriching) {
                $lead->refresh();
                $lead->transitionTo($lead->emails()->exists() ? LeadStatus::Enriched : LeadStatus::NeedsInfo);
            }
        }

        CheckProgress::done($lead->id, $this->summary($lead));
    }

    /**
     * One line for the finished progress panel.
     */
    private function summary(Lead $lead): string
    {
        $crawl = $lead->websiteChecks()->where('type', WebsiteCheckType::Crawl)->first();

        if ($crawl === null) {
            return 'Check finished.';
        }

        if ($crawl->failed()) {
            return 'Check finished: '.$crawl->error;
        }

        $emails = (int) ($crawl->result['emails_found'] ?? 0);
        $socials = (int) ($crawl->result['socials_found'] ?? 0);

        return match (true) {
            filled($crawl->result['blocked_by'] ?? null) => 'Check finished: the site blocks automated visits ('.$crawl->result['blocked_by'].').',
            default => "Check finished: found {$emails} ".str('email')->plural($emails)." and {$socials} social ".str('profile')->plural($socials).'.',
        };
    }

    /**
     * Leads early in the pipeline show "Enriching" while the check runs.
     * Leads further along keep their status; only their data is refreshed.
     */
    private function startEnriching(Lead $lead): bool
    {
        if (! in_array($lead->status, [LeadStatus::New, LeadStatus::NeedsInfo, LeadStatus::Enriched], true)) {
            return false;
        }

        $lead->transitionTo(LeadStatus::Enriching);

        return true;
    }
}
