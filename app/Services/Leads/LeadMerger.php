<?php

namespace App\Services\Leads;

use App\Enums\LeadEventType;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Folds a duplicate lead into the one being kept. The kept lead's own values
 * and status win; the duplicate only fills gaps. Emails, socials, search
 * links and the timeline move across, and a snapshot of the duplicate is
 * written to the timeline before it is deleted.
 */
class LeadMerger
{
    /**
     * Fields copied from the duplicate when the kept lead has none.
     *
     * @var list<string>
     */
    private const FILLABLE_GAPS = [
        'phone', 'phone_e164', 'website', 'street', 'city', 'region', 'postal_code', 'country',
        'lat', 'lng', 'timezone', 'category', 'rating', 'review_count', 'maps_url', 'owner_id',
        'opportunity_score', 'fit_score',
    ];

    public function merge(Lead $keep, Lead $duplicate, ?User $by = null): Lead
    {
        if ($keep->is($duplicate)) {
            throw new InvalidArgumentException('A lead cannot be merged into itself.');
        }

        return DB::transaction(function () use ($keep, $duplicate, $by): Lead {
            $snapshot = $duplicate->only(['id', 'name', 'place_id', 'phone', 'website', 'city', 'status', 'source']);
            $snapshot['status'] = $duplicate->status->value;
            $snapshot['source'] = $duplicate->source->value;

            $this->fillGaps($keep, $duplicate);
            $this->moveEmails($keep, $duplicate);
            $this->moveSocials($keep, $duplicate);
            $this->moveSearchLinks($keep, $duplicate);

            DB::table('lead_events')->where('lead_id', $duplicate->id)->update(['lead_id' => $keep->id]);

            $keep->events()->create([
                'type' => LeadEventType::Merged,
                'user_id' => $by?->getKey(),
                'data' => ['duplicate' => $snapshot],
            ]);

            $duplicate->delete();

            return $keep->refresh();
        });
    }

    private function fillGaps(Lead $keep, Lead $duplicate): void
    {
        $placeId = $keep->place_id === null ? $duplicate->place_id : null;

        if ($placeId !== null) {
            $duplicate->forceFill(['place_id' => null])->saveQuietly();
        }

        foreach (self::FILLABLE_GAPS as $field) {
            if (blank($keep->getAttribute($field)) && filled($duplicate->getAttribute($field))) {
                $keep->setAttribute($field, $duplicate->getAttribute($field));
            }
        }

        $keep->forceFill(array_filter(['place_id' => $placeId]))->save();
    }

    private function moveEmails(Lead $keep, Lead $duplicate): void
    {
        $existing = $keep->emails()->pluck('email')->all();
        $hasPrimary = $keep->emails()->where('is_primary', true)->exists();

        foreach ($duplicate->emails()->get() as $email) {
            if (in_array($email->email, $existing, true)) {
                $email->delete();

                continue;
            }

            $email->forceFill([
                'lead_id' => $keep->id,
                'is_primary' => $email->is_primary && ! $hasPrimary,
            ])->save();

            $hasPrimary = $hasPrimary || $email->is_primary;
        }
    }

    private function moveSocials(Lead $keep, Lead $duplicate): void
    {
        $existing = $keep->socials()->pluck('url')->all();

        foreach ($duplicate->socials()->get() as $social) {
            in_array($social->url, $existing, true)
                ? $social->delete()
                : $social->forceFill(['lead_id' => $keep->id])->save();
        }
    }

    private function moveSearchLinks(Lead $keep, Lead $duplicate): void
    {
        $links = DB::table('lead_search')->where('lead_id', $duplicate->id)->get();

        DB::table('lead_search')->insertOrIgnore($links->map(fn (object $link): array => [
            'lead_id' => $keep->id,
            'search_id' => $link->search_id,
            'rank' => $link->rank,
            'was_new' => $link->was_new,
            'created_at' => $link->created_at,
            'updated_at' => now(),
        ])->all());

        DB::table('lead_search')->where('lead_id', $duplicate->id)->delete();
    }
}
