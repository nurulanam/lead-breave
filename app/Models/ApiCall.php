<?php

namespace App\Models;

use Database\Factories\ApiCallFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One billable or rate-limited call to an external API. Spend caps and
 * reports are counted from this ledger, never from searches.
 */
#[Fillable(['provider', 'endpoint', 'cost_units', 'status', 'duration_ms', 'related_type', 'related_id'])]
class ApiCall extends Model
{
    /** @use HasFactory<ApiCallFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost_units' => 'integer',
            'status' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    /**
     * Record one call in the ledger.
     */
    public static function record(string $provider, string $endpoint, ?int $status, int $durationMs, int $costUnits = 1, ?Model $related = null): self
    {
        return static::query()->create([
            'provider' => $provider,
            'endpoint' => $endpoint,
            'status' => $status,
            'duration_ms' => $durationMs,
            'cost_units' => $costUnits,
            'related_type' => $related?->getMorphClass(),
            'related_id' => $related?->getKey(),
        ]);
    }

    /**
     * @param  Builder<ApiCall>  $query
     */
    #[Scope]
    protected function forProvider(Builder $query, string $provider): void
    {
        $query->where('provider', $provider);
    }

    /**
     * @param  Builder<ApiCall>  $query
     */
    #[Scope]
    protected function since(Builder $query, Carbon $from): void
    {
        $query->where('created_at', '>=', $from);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function related(): MorphTo
    {
        return $this->morphTo();
    }
}
