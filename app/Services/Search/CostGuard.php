<?php

namespace App\Services\Search;

use App\Models\ApiCall;
use App\Models\Search;
use App\Settings\SearchSettings;

/**
 * Stops a search before it spends past the per-search or monthly cap.
 * Spend is counted from the api_calls ledger, so it includes every search.
 */
class CostGuard
{
    public function __construct(private SearchSettings $settings) {}

    public function stopReason(Search $search): ?string
    {
        if ($search->api_cost_units >= $this->settings->per_search_request_cap) {
            return "Reached the limit of {$this->settings->per_search_request_cap} Google requests for one search.";
        }

        if ($this->placesRequestsThisMonth() >= $this->settings->monthly_request_cap) {
            return "Reached this month's limit of {$this->settings->monthly_request_cap} Google requests. Raise it under Settings > Search.";
        }

        return null;
    }

    public function placesRequestsThisMonth(): int
    {
        return (int) ApiCall::query()
            ->forProvider('google_places')
            ->since(now()->startOfMonth())
            ->sum('cost_units');
    }
}
