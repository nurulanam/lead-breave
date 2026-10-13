<?php

namespace App\Services\Leads;

use App\Models\Lead;
use App\Models\LeadEmail;
use App\Support\Domain;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Decides whether a business is one we already hold. Keys, in order of
 * confidence: Google place id, website domain (shared hosts such as
 * facebook.com never count), E.164 phone, then email address.
 *
 * Load a batch of candidates first (one query for the batch), then look each
 * one up. Leads created during the batch are remembered, so later rows match them.
 */
class LeadMatcher
{
    /** @var array<string, Lead> */
    private array $byPlaceId = [];

    /** @var array<string, Lead> */
    private array $byDomain = [];

    /** @var array<string, Lead> */
    private array $byPhone = [];

    /** @var array<string, Lead> */
    private array $byEmail = [];

    /**
     * @param  list<array{place_id?: string|null, domain?: string|null, phone?: string|null, email?: string|null}>  $candidates
     */
    public function load(array $candidates): void
    {
        $placeIds = $this->values($candidates, 'place_id');
        $domains = array_values(array_filter($this->values($candidates, 'domain'), fn (string $domain): bool => ! Domain::isShared($domain)));
        $phones = $this->values($candidates, 'phone');
        $emails = array_map('mb_strtolower', $this->values($candidates, 'email'));

        $emailOwners = $emails === [] ? [] : LeadEmail::query()->whereIn('email', $emails)->pluck('lead_id', 'email')->all();

        if ($placeIds === [] && $domains === [] && $phones === [] && $emailOwners === []) {
            return;
        }

        $leads = Lead::query()
            ->where(function (Builder $query) use ($placeIds, $domains, $phones, $emailOwners): void {
                $query->whereIn('place_id', $placeIds)
                    ->orWhereIn('domain', $domains)
                    ->orWhereIn('phone_e164', $phones)
                    ->orWhereIn('id', array_values($emailOwners));
            })
            ->get()
            ->keyBy('id');

        foreach ($leads as $lead) {
            $this->remember($lead);
        }

        foreach ($emailOwners as $email => $leadId) {
            if ($leads->has($leadId)) {
                $this->byEmail[(string) $email] = $leads->get($leadId);
            }
        }
    }

    public function find(?string $placeId = null, ?string $domain = null, ?string $phone = null, ?string $email = null): ?Lead
    {
        return ($placeId !== null ? $this->byPlaceId[$placeId] ?? null : null)
            ?? (! Domain::isShared($domain) ? $this->byDomain[(string) $domain] ?? null : null)
            ?? ($phone !== null ? $this->byPhone[$phone] ?? null : null)
            ?? ($email !== null ? $this->byEmail[mb_strtolower($email)] ?? null : null);
    }

    /**
     * One-off lookup, for a single lead typed in by hand.
     */
    public function match(?string $placeId = null, ?string $domain = null, ?string $phone = null, ?string $email = null): ?Lead
    {
        $this->load([['place_id' => $placeId, 'domain' => $domain, 'phone' => $phone, 'email' => $email]]);

        return $this->find($placeId, $domain, $phone, $email);
    }

    public function remember(Lead $lead, ?string $email = null): void
    {
        if ($lead->place_id !== null) {
            $this->byPlaceId[$lead->place_id] = $lead;
        }

        if (! Domain::isShared($lead->domain)) {
            $this->byDomain[(string) $lead->domain] = $lead;
        }

        if ($lead->phone_e164 !== null) {
            $this->byPhone[$lead->phone_e164] = $lead;
        }

        if ($email !== null) {
            $this->byEmail[mb_strtolower($email)] = $lead;
        }
    }

    /**
     * Other leads that look like the same business: same domain, phone, email,
     * or the same name in the same city.
     *
     * @return Collection<int, Lead>
     */
    public static function possibleDuplicates(Lead $lead, int $limit = 10): Collection
    {
        $emails = $lead->emails()->pluck('email')->all();

        return Lead::query()
            ->whereKeyNot($lead->getKey())
            ->where(function (Builder $query) use ($lead, $emails): void {
                if (! Domain::isShared($lead->domain)) {
                    $query->orWhere('domain', $lead->domain);
                }

                if ($lead->phone_e164 !== null) {
                    $query->orWhere('phone_e164', $lead->phone_e164);
                }

                if ($emails !== []) {
                    $query->orWhereHas('emails', fn (Builder $emailQuery) => $emailQuery->whereIn('email', $emails));
                }

                if ($lead->city !== null) {
                    $query->orWhere(fn (Builder $sameName) => $sameName
                        ->whereRaw('lower(name) = ?', [mb_strtolower($lead->name)])
                        ->whereRaw('lower(city) = ?', [mb_strtolower($lead->city)]));
                }
            })
            ->limit($limit)
            ->get();
    }

    /**
     * @param  list<array<string, string|null>>  $candidates
     * @return list<string>
     */
    private function values(array $candidates, string $key): array
    {
        return array_values(array_unique(array_filter(array_column($candidates, $key), fn (?string $value): bool => filled($value))));
    }
}
