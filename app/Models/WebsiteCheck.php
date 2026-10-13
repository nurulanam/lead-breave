<?php

namespace App\Models;

use App\Enums\WebsiteCheckType;
use Database\Factories\WebsiteCheckFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The latest result of one kind of check on a lead's website.
 *
 * @property WebsiteCheckType $type
 * @property array<string, mixed>|null $result
 */
#[Fillable(['lead_id', 'type', 'status', 'result', 'error', 'checked_at'])]
class WebsiteCheck extends Model
{
    /** @use HasFactory<WebsiteCheckFactory> */
    use HasFactory;

    public const STATUS_OK = 'ok';

    public const STATUS_FAILED = 'failed';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => WebsiteCheckType::class,
            'result' => 'array',
            'checked_at' => 'datetime',
        ];
    }

    public function failed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
