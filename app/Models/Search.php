<?php

namespace App\Models;

use App\Enums\SearchStatus;
use App\Enums\TilingMode;
use Database\Factories\SearchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'industry', 'location', 'zip', 'country', 'center_lat', 'center_lng', 'radius_m',
    'tiling', 'status', 'tiles_total', 'tiles_done', 'results_found', 'leads_created',
    'leads_linked', 'api_cost_units', 'stop_reason', 'started_at', 'completed_at',
])]
class Search extends Model
{
    /** @use HasFactory<SearchFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'tiling' => 'grid',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SearchStatus::class,
            'tiling' => TilingMode::class,
            'center_lat' => 'float',
            'center_lng' => 'float',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Share of tiles finished, 0–100.
     */
    public function progress(): int
    {
        return $this->tiles_total > 0 ? (int) floor($this->tiles_done / $this->tiles_total * 100) : 0;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<SearchTile, $this>
     */
    public function tiles(): HasMany
    {
        return $this->hasMany(SearchTile::class);
    }

    /**
     * @return BelongsToMany<Lead, $this>
     */
    public function leads(): BelongsToMany
    {
        return $this->belongsToMany(Lead::class)
            ->withPivot(['rank', 'was_new'])
            ->withTimestamps();
    }
}
