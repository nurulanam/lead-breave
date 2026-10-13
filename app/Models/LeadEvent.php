<?php

namespace App\Models;

use App\Enums\LeadEventType;
use App\Enums\LeadStatus;
use Database\Factories\LeadEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An entry on a lead's activity timeline. Events are append-only.
 *
 * @property LeadEventType $type
 * @property array<string, mixed>|null $data
 */
#[Fillable(['lead_id', 'user_id', 'type', 'data'])]
class LeadEvent extends Model
{
    /** @use HasFactory<LeadEventFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LeadEventType::class,
            'data' => 'array',
        ];
    }

    /**
     * One line for the activity timeline.
     */
    public function summary(): string
    {
        $data = $this->data ?? [];

        return match ($this->type) {
            LeadEventType::Created => 'Added from '.match ($data['source'] ?? null) {
                'manual' => 'manual entry',
                'csv' => 'a CSV import',
                default => 'Google Maps',
            },
            LeadEventType::StatusChanged => 'Moved from '.(LeadStatus::tryFrom((string) ($data['from'] ?? ''))?->label() ?? '?')
                .' to '.(LeadStatus::tryFrom((string) ($data['to'] ?? ''))?->label() ?? '?'),
            LeadEventType::LinkedToSearch => 'Found again by the search "'.($data['query'] ?? 'unknown').'"',
            LeadEventType::Edited => 'Changed '.implode(', ', array_map(
                fn (string $field): string => str_replace('_', ' ', $field),
                (array) ($data['fields'] ?? []),
            )),
            LeadEventType::OwnerChanged => isset($data['to_name']) ? 'Assigned to '.$data['to_name'] : 'Unassigned',
            LeadEventType::Merged => 'Merged the duplicate "'.($data['duplicate']['name'] ?? 'unknown').'" into this lead',
            LeadEventType::Imported => 'Updated from the import "'.($data['file'] ?? 'CSV').'"',
        };
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
