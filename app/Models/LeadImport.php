<?php

namespace App\Models;

use Database\Factories\LeadImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One CSV import: the stored file, how its columns map to lead fields, and
 * running totals. Rows are processed in chunks by ImportLeadsCsv.
 *
 * @property array<string, string|null> $mapping
 * @property list<array{row: int, reason: string}>|null $errors
 */
#[Fillable([
    'user_id', 'file_path', 'original_name', 'mapping', 'default_country', 'status', 'total_rows',
    'processed_rows', 'created_count', 'updated_count', 'invalid_count', 'errors', 'started_at', 'completed_at',
])]
class LeadImport extends Model
{
    /** @use HasFactory<LeadImportFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /** Only the first errors are kept; the counts cover them all. */
    public const MAX_STORED_ERRORS = 500;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mapping' => 'array',
            'errors' => 'array',
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'created_count' => 'integer',
            'updated_count' => 'integer',
            'invalid_count' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED], true);
    }

    public function progress(): int
    {
        return $this->total_rows > 0 ? (int) floor($this->processed_rows / $this->total_rows * 100) : 0;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
