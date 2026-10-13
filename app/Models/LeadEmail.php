<?php

namespace App\Models;

use App\Enums\DataSource;
use Database\Factories\LeadEmailFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * @property DataSource $source
 * @property bool $is_primary
 * @property bool $is_role
 * @property int $confidence
 */
#[Fillable(['lead_id', 'email', 'source', 'found_on_url', 'confidence', 'is_role', 'is_primary', 'verified_status'])]
class LeadEmail extends Model
{
    /** @use HasFactory<LeadEmailFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => DataSource::class,
            'confidence' => 'integer',
            'is_role' => 'boolean',
            'is_primary' => 'boolean',
        ];
    }

    /**
     * Addresses are stored lowercased and trimmed, so the unique index catches duplicates.
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => mb_strtolower(trim($value)));
    }

    /**
     * Make this the lead's only primary address.
     */
    public function makePrimary(): void
    {
        DB::transaction(function (): void {
            static::query()
                ->where('lead_id', $this->lead_id)
                ->whereKeyNot($this->getKey())
                ->update(['is_primary' => false]);

            $this->forceFill(['is_primary' => true])->save();
        });
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
