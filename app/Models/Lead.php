<?php

namespace App\Models;

use App\Enums\LeadEventType;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Exceptions\InvalidLeadTransition;
use App\Support\Domain;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

/**
 * A local business. The status column is not fillable: change it with
 * transitionTo() so every move is checked and logged.
 *
 * @property LeadStatus $status
 * @property LeadSource $source
 * @property string|null $website
 * @property string|null $domain
 */
#[Fillable([
    'place_id', 'name', 'phone', 'phone_e164', 'website', 'street', 'city', 'region',
    'postal_code', 'country', 'lat', 'lng', 'timezone', 'category', 'rating', 'review_count',
    'maps_url', 'owner_id', 'source', 'opportunity_score', 'fit_score', 'next_action_at',
    'last_contacted_at',
])]
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'new',
        'source' => 'places',
    ];

    protected static function booted(): void
    {
        static::created(function (Lead $lead): void {
            $lead->events()->create([
                'type' => LeadEventType::Created,
                'user_id' => auth()->id(),
                'data' => ['source' => $lead->source->value],
            ]);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'source' => LeadSource::class,
            'lat' => 'float',
            'lng' => 'float',
            'rating' => 'float',
            'review_count' => 'integer',
            'opportunity_score' => 'integer',
            'fit_score' => 'integer',
            'next_action_at' => 'datetime',
            'last_contacted_at' => 'datetime',
        ];
    }

    /**
     * Setting the website also sets the domain used for dedupe.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function website(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): array => [
                'website' => filled($value) ? trim($value) : null,
                'domain' => Domain::fromUrl($value),
            ],
        );
    }

    /**
     * Move the lead to a new status. The current status is re-read under a
     * row lock, so two workers cannot both make a transition from the same state.
     *
     * @param  array<string, mixed>  $data  Extra context stored on the timeline event.
     *
     * @throws InvalidLeadTransition
     */
    public function transitionTo(LeadStatus $status, ?User $by = null, array $data = []): void
    {
        DB::transaction(function () use ($status, $by, $data): void {
            $current = static::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail(['id', 'status'])->status;

            if (! $current->canTransitionTo($status)) {
                throw InvalidLeadTransition::between($current, $status);
            }

            $this->forceFill(['status' => $status])->save();

            $this->events()->create([
                'type' => LeadEventType::StatusChanged,
                'user_id' => $by?->getKey() ?? auth()->id(),
                'data' => ['from' => $current->value, 'to' => $status->value, ...$data],
            ]);
        });
    }

    /**
     * Match on name and city: a full-text search on MySQL, a LIKE elsewhere.
     *
     * @param  Builder<Lead>  $query
     */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $term = trim($term);

        if ($term === '') {
            return;
        }

        if (in_array($query->getModel()->getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $query->whereFullText(['name', 'city'], $term);

            return;
        }

        $query->where(fn (Builder $query) => $query
            ->where('name', 'like', "%{$term}%")
            ->orWhere('city', 'like', "%{$term}%"));
    }

    /**
     * @param  Builder<Lead>  $query
     */
    #[Scope]
    protected function withStatus(Builder $query, LeadStatus ...$statuses): void
    {
        $query->whereIn('status', $statuses);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsToMany<Search, $this>
     */
    public function searches(): BelongsToMany
    {
        return $this->belongsToMany(Search::class)
            ->withPivot(['rank', 'was_new'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<LeadEmail, $this>
     */
    public function emails(): HasMany
    {
        return $this->hasMany(LeadEmail::class);
    }

    /**
     * @return HasOne<LeadEmail, $this>
     */
    public function primaryEmail(): HasOne
    {
        return $this->hasOne(LeadEmail::class)->where('is_primary', true);
    }

    /**
     * @return HasMany<LeadSocial, $this>
     */
    public function socials(): HasMany
    {
        return $this->hasMany(LeadSocial::class);
    }

    /**
     * @return HasMany<LeadEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(LeadEvent::class);
    }
}
