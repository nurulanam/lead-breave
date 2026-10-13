<?php

namespace App\Settings;

use App\Enums\ApiKey;
use App\Models\User;
use Illuminate\Support\Carbon;
use Spatie\LaravelSettings\Settings;
use stdClass;

/**
 * Third-party API keys. Stored encrypted and never sent to the browser: the
 * UI only ever sees the masked form from masked().
 */
class ApiSettings extends Settings
{
    /**
     * One Google Cloud key for Places API (New), Geocoding API and PageSpeed Insights API.
     */
    public ?string $google_api_key;

    public ?string $anthropic_key;

    /**
     * JSON: per key, who changed it and when, and its last connection test.
     * Read and written through meta() and writeMeta().
     */
    public string $key_meta;

    public static function group(): string
    {
        return 'api';
    }

    /**
     * @return list<string>
     */
    public static function encrypted(): array
    {
        return self::keys();
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_map(fn (ApiKey $key): string => $key->value, ApiKey::cases());
    }

    public function has(string $key): bool
    {
        return filled($this->{$key} ?? null);
    }

    public function value(ApiKey $key): ?string
    {
        return $this->{$key->value};
    }

    /**
     * Store a new key (or remove it with null), recording who did it. Any
     * earlier test result is dropped, because it described the old key.
     */
    public function replace(ApiKey $key, ?string $value, ?User $by): void
    {
        $this->{$key->value} = $value;

        $meta = $this->meta();

        if ($value === null) {
            unset($meta[$key->value]);
        } else {
            $meta[$key->value] = ['updated_at' => now()->toIso8601String(), 'updated_by' => $by?->name];
        }

        $this->writeMeta($meta);
        $this->save();
    }

    /**
     * @param  array<string, array{ok: bool, message: string}>  $services
     */
    public function recordCheck(ApiKey $key, array $services): void
    {
        $meta = $this->meta();
        $meta[$key->value] = [
            ...($meta[$key->value] ?? []),
            'check' => ['checked_at' => now()->toIso8601String(), 'services' => $services],
        ];

        $this->writeMeta($meta);
        $this->save();
    }

    /**
     * The key with only its last four characters shown, after the public
     * prefix every key of that kind shares ("AIza", "sk-ant-").
     */
    public function masked(ApiKey $key): ?string
    {
        $value = $this->value($key);

        if (blank($value)) {
            return null;
        }

        $shown = str_starts_with($value, $key->publicPrefix()) ? $key->publicPrefix() : '';

        return $shown.str_repeat('•', 12).mb_substr($value, -4);
    }

    public function updatedAt(ApiKey $key): ?Carbon
    {
        $at = $this->meta()[$key->value]['updated_at'] ?? null;

        return is_string($at) ? Carbon::parse($at) : null;
    }

    public function updatedBy(ApiKey $key): ?string
    {
        $by = $this->meta()[$key->value]['updated_by'] ?? null;

        return is_string($by) ? $by : null;
    }

    /**
     * @return array{checked_at: string, services: array<string, array{ok: bool, message: string}>}|null
     */
    public function lastCheck(ApiKey $key): ?array
    {
        $check = $this->meta()[$key->value]['check'] ?? null;

        return is_array($check) ? $check : null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function meta(): array
    {
        $meta = json_decode($this->key_meta ?: '{}', true);

        return is_array($meta) ? $meta : [];
    }

    /**
     * @param  array<string, array<string, mixed>>  $meta
     */
    private function writeMeta(array $meta): void
    {
        $this->key_meta = (string) json_encode($meta === [] ? new stdClass : $meta);
    }
}
