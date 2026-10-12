<?php

namespace App\Models;

use App\Enums\TileStatus;
use Database\Factories\SearchTileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One cell of a search's grid. Each cell is fetched page by page, so a cell
 * is the unit of locking, retrying and billing.
 */
#[Fillable([
    'search_id', 'parent_id', 'depth', 'lat', 'lng', 'radius_m', 'page', 'next_page_token',
    'status', 'results', 'attempts', 'error',
])]
#[Hidden(['next_page_token'])]
class SearchTile extends Model
{
    /** @use HasFactory<SearchTileFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TileStatus::class,
            'lat' => 'float',
            'lng' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Search, $this>
     */
    public function search(): BelongsTo
    {
        return $this->belongsTo(Search::class);
    }

    /**
     * @return BelongsTo<SearchTile, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(SearchTile::class, 'parent_id');
    }

    /**
     * @return HasMany<SearchTile, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(SearchTile::class, 'parent_id');
    }
}
