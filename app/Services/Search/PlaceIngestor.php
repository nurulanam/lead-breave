<?php

namespace App\Services\Search;

use App\Enums\LeadEventType;
use App\Enums\LeadSource;
use App\Jobs\CheckWebsite;
use App\Models\Lead;
use App\Models\Search;
use App\Services\Google\Place;
use App\Services\Leads\LeadMatcher;
use App\Support\Domain;
use App\Support\Phone;
use App\Support\Timezones;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Turns one page of Places results into leads. Existing businesses are
 * matched by place id, then website domain, then phone, in a single query
 * per page, and linked to the search rather than skipped.
 */
class PlaceIngestor
{
    private LeadMatcher $matcher;

    public function __construct()
    {
        $this->matcher = new LeadMatcher;
    }

    /**
     * @param  list<Place>  $places
     */
    public function ingest(Search $search, array $places, int $firstRank = 1): IngestResult
    {
        $places = array_values(array_filter($places, fn (Place $place): bool => ! $place->isPermanentlyClosed));

        if ($places === []) {
            return new IngestResult(0, 0, 0);
        }

        $normalized = array_map(fn (Place $place): array => [
            'place' => $place,
            'domain' => $this->matchableDomain($place->website),
            'phone' => Phone::toE164($place->internationalPhone, $place->phone, $place->country ?? $search->country),
        ], $places);

        $this->matcher->load(array_map(fn (array $row): array => [
            'place_id' => $row['place']->id,
            'domain' => $row['domain'],
            'phone' => $row['phone'],
        ], $normalized));

        $newLinks = [];
        $existingLinks = [];

        foreach ($normalized as $index => ['place' => $place, 'domain' => $domain, 'phone' => $phone]) {
            $lead = $this->matcher->find($place->id, $domain, $phone);

            $isNew = $lead === null;
            $lead = $isNew ? $this->create($place, $phone) : $this->refresh($lead, $place, $phone);

            $this->matcher->remember($lead);

            $row = ['lead_id' => $lead->id, 'search_id' => $search->id, 'rank' => $firstRank + $index, 'created_at' => now(), 'updated_at' => now()];

            if ($lead->wasRecentlyCreated) {
                $newLinks[$lead->id] = [...$row, 'was_new' => true];
            } else {
                $existingLinks[$lead->id] ??= [...$row, 'was_new' => false];
            }
        }

        $existingLinks = array_diff_key($existingLinks, $newLinks);

        $created = DB::table('lead_search')->insertOrIgnore(array_values($newLinks));
        $linked = $this->linkExisting($search, $existingLinks);

        return new IngestResult(count($places), $created, $linked);
    }

    /**
     * Create the lead. If another worker created the same place a moment ago
     * (neighbouring cells share edges), refresh that lead instead.
     */
    private function create(Place $place, ?string $phone): Lead
    {
        try {
            $lead = Lead::query()->create([
                ...$this->placeAttributes($place),
                'place_id' => $place->id,
                'name' => $place->name,
                'phone' => $place->phone ?? $place->internationalPhone,
                'phone_e164' => $phone,
                'website' => $place->website,
                'source' => LeadSource::Places,
            ]);

            CheckWebsite::forLead($lead);

            return $lead;
        } catch (UniqueConstraintViolationException) {
            return $this->refresh(Lead::query()->where('place_id', $place->id)->firstOrFail(), $place, $phone);
        }
    }

    /**
     * Refresh what Google knows best, and only fill contact details that are
     * still empty, so hand-entered values survive.
     */
    private function refresh(Lead $lead, Place $place, ?string $phone): Lead
    {
        $lead->fill(array_filter($this->placeAttributes($place), fn (mixed $value): bool => $value !== null));
        $lead->place_id ??= $place->id;

        if (blank($lead->phone_e164) && $phone !== null) {
            $lead->fill(['phone' => $place->phone ?? $place->internationalPhone, 'phone_e164' => $phone]);
        }

        $gainedWebsite = blank($lead->website) && filled($place->website);

        if ($gainedWebsite) {
            $lead->website = $place->website;
        }

        $lead->save();

        if ($gainedWebsite) {
            CheckWebsite::forLead($lead);
        }

        return $lead;
    }

    /**
     * @return array<string, mixed>
     */
    private function placeAttributes(Place $place): array
    {
        return [
            'street' => $place->street,
            'city' => $place->city,
            'region' => $place->region,
            'postal_code' => $place->postalCode,
            'country' => $place->country,
            'lat' => $place->location?->lat,
            'lng' => $place->location?->lng,
            'timezone' => Timezones::guess($place->country, $place->region, $place->location?->lat, $place->location?->lng),
            'category' => $place->category,
            'rating' => $place->rating,
            'review_count' => $place->reviewCount,
            'maps_url' => $place->mapsUrl,
        ];
    }

    /**
     * Link leads found before to this search, and log it on their timeline.
     *
     * @param  array<int, array<string, mixed>>  $links
     */
    private function linkExisting(Search $search, array $links): int
    {
        if ($links === []) {
            return 0;
        }

        $alreadyLinked = DB::table('lead_search')
            ->where('search_id', $search->id)
            ->whereIn('lead_id', array_keys($links))
            ->pluck('lead_id')
            ->all();

        $links = array_diff_key($links, array_flip($alreadyLinked));
        $inserted = DB::table('lead_search')->insertOrIgnore(array_values($links));

        foreach (array_keys($links) as $leadId) {
            DB::table('lead_events')->insert([
                'lead_id' => $leadId,
                'type' => LeadEventType::LinkedToSearch->value,
                'data' => json_encode(['search_id' => $search->id, 'query' => "{$search->industry} in {$search->location}"]),
                'created_at' => now(),
            ]);
        }

        return $inserted;
    }

    private function matchableDomain(?string $website): ?string
    {
        $domain = Domain::fromUrl($website);

        return Domain::isShared($domain) ? null : $domain;
    }
}
