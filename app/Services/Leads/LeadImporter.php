<?php

namespace App\Services\Leads;

use App\Enums\DataSource;
use App\Enums\LeadEventType;
use App\Enums\LeadSource;
use App\Enums\SocialPlatform;
use App\Models\Lead;
use App\Models\LeadImport;
use App\Support\Countries;
use App\Support\Domain;
use Generator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Turns CSV rows into leads with the same dedupe as search: new businesses
 * are created, known ones only have their gaps filled. The preview runs the
 * same rules without writing anything.
 */
class LeadImporter
{
    public const CHUNK = 250;

    /** @var array<string, SocialPlatform> */
    private const SOCIAL_FIELDS = [
        'facebook' => SocialPlatform::Facebook,
        'instagram' => SocialPlatform::Instagram,
        'linkedin' => SocialPlatform::LinkedIn,
        'youtube' => SocialPlatform::YouTube,
    ];

    public function __construct(private CsvLeadReader $reader) {}

    /**
     * Count what an import would do, and show the first rows.
     *
     * @param  array<string, string|null>  $mapping
     * @return array{total: int, new: int, duplicate: int, invalid: int, rows: list<array{line: int, name: string|null, outcome: string, detail: string}>}
     */
    public function preview(string $path, array $mapping, string $defaultCountry, int $sample = 8): array
    {
        $summary = ['total' => 0, 'new' => 0, 'duplicate' => 0, 'invalid' => 0, 'rows' => []];
        $seen = [];

        foreach ($this->chunks($path) as $chunk) {
            $prepared = array_map(fn (array $row): array => $this->prepare($row, $mapping, $defaultCountry), $chunk);
            $matcher = new LeadMatcher;
            $matcher->load(array_values(array_filter(array_column($prepared, 'keys'))));

            foreach ($prepared as $row) {
                $summary['total']++;

                if ($row['error'] !== null) {
                    $outcome = ['invalid', $row['error']];
                } elseif (($existing = $matcher->find(...$row['keys'])) !== null) {
                    $outcome = ['duplicate', 'Matches '.$existing->name];
                } elseif ($this->seenBefore($seen, $row['keys'])) {
                    $outcome = ['duplicate', 'Repeats an earlier row in this file'];
                } else {
                    $outcome = ['new', 'Will be added'];
                }

                $summary[$outcome[0]]++;

                if (count($summary['rows']) < $sample) {
                    $summary['rows'][] = ['line' => $row['line'], 'name' => $row['data']['name'] ?? null, 'outcome' => $outcome[0], 'detail' => $outcome[1]];
                }
            }
        }

        return $summary;
    }

    /**
     * Import one chunk of rows starting at $offset. Returns how many rows it read.
     */
    public function importChunk(LeadImport $import, string $path, int $offset): int
    {
        $rows = [];

        foreach ($this->reader->rows($path, offset: $offset) as $row) {
            $rows[] = $row;

            if (count($rows) === self::CHUNK) {
                break;
            }
        }

        if ($rows === []) {
            return 0;
        }

        $prepared = array_map(fn (array $row): array => $this->prepare($row, $import->mapping, $import->default_country), $rows);
        $matcher = new LeadMatcher;
        $matcher->load(array_values(array_filter(array_column($prepared, 'keys'))));

        $created = $updated = 0;
        $errors = [];

        DB::transaction(function () use ($prepared, $matcher, $import, &$created, &$updated, &$errors): void {
            foreach ($prepared as $row) {
                if ($row['error'] !== null) {
                    $errors[] = ['row' => $row['line'], 'reason' => $row['error']];

                    continue;
                }

                $existing = $matcher->find(...$row['keys']);

                if ($existing === null) {
                    $lead = Lead::query()->create([...$row['lead'], 'source' => LeadSource::Csv]);
                    $created++;
                } else {
                    $lead = $this->fillGaps($existing, $row['lead'], $import);
                    $updated++;
                }

                $this->attachContacts($lead, $row['data']);
                $matcher->remember($lead, $row['data']['email'] ?? null);
            }
        });

        $import->forceFill([
            'processed_rows' => $import->processed_rows + count($rows),
            'created_count' => $import->created_count + $created,
            'updated_count' => $import->updated_count + $updated,
            'invalid_count' => $import->invalid_count + count($errors),
            'errors' => array_slice([...($import->errors ?? []), ...$errors], 0, LeadImport::MAX_STORED_ERRORS),
        ])->save();

        return count($rows);
    }

    /**
     * @return Generator<int, list<array{line: int, values: array<int|string, string|null>}>>
     */
    private function chunks(string $path): Generator
    {
        $chunk = [];

        foreach ($this->reader->rows($path) as $row) {
            $chunk[] = $row;

            if (count($chunk) === self::CHUNK) {
                yield $chunk;
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            yield $chunk;
        }
    }

    /**
     * Map, tidy and validate one row.
     *
     * @param  array{line: int, values: array<int|string, string|null>}  $row
     * @param  array<string, string|null>  $mapping
     * @return array{line: int, data: array<string, string|null>, lead: array<string, mixed>, keys: array{domain: string|null, phone: string|null, email: string|null}|null, error: string|null}
     */
    private function prepare(array $row, array $mapping, string $defaultCountry): array
    {
        $data = [];

        foreach (CsvLeadReader::FIELDS as $field => $definition) {
            $column = $mapping[$field] ?? null;
            $data[$field] = $column !== null ? ($row['values'][$column] ?? null) : null;
        }

        $data['country'] = $this->countryCode($data['country']) ?? strtoupper($defaultCountry);
        $data = LeadInput::normalize($data);

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'regex:/^https?:\/\/[^\s\/$.?#]+\.[^\s]+$/i', 'max:2048'],
        ], [
            'name.required' => 'No business name',
            'email.email' => 'The email address is not valid',
            'website.regex' => 'The website is not a web address',
        ]);

        $error = $validator->fails() ? $validator->errors()->first() : null;

        return [
            'line' => $row['line'],
            'data' => $data,
            'lead' => array_filter([
                'name' => $data['name'],
                'website' => $data['website'],
                'phone' => $data['phone'],
                'phone_e164' => $data['phone_e164'],
                'category' => $data['category'],
                'street' => $data['street'],
                'city' => $data['city'],
                'region' => $data['region'],
                'postal_code' => $data['postal_code'],
                'country' => $data['country'],
            ], fn (mixed $value): bool => $value !== null),
            'keys' => $error === null ? [
                'domain' => Domain::fromUrl($data['website']),
                'phone' => $data['phone_e164'],
                'email' => $data['email'],
            ] : null,
            'error' => $error,
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function fillGaps(Lead $lead, array $values, LeadImport $import): Lead
    {
        $filled = [];

        foreach ($values as $field => $value) {
            if ($field !== 'name' && blank($lead->getAttribute($field))) {
                $lead->setAttribute($field, $value);
                $filled[] = $field;
            }
        }

        $lead->save();

        $lead->events()->create([
            'type' => LeadEventType::Imported,
            'user_id' => $import->user_id,
            'data' => ['import_id' => $import->id, 'file' => $import->original_name, 'fields' => $filled],
        ]);

        return $lead;
    }

    /**
     * @param  array<string, string|null>  $data
     */
    private function attachContacts(Lead $lead, array $data): void
    {
        if ($data['email'] !== null && $lead->emails()->where('email', $data['email'])->doesntExist()) {
            $email = $lead->emails()->create(['email' => $data['email'], 'source' => DataSource::Csv, 'confidence' => 80]);

            if ($lead->emails()->where('is_primary', true)->doesntExist()) {
                $email->makePrimary();
            }
        }

        foreach (self::SOCIAL_FIELDS as $field => $platform) {
            $url = LeadInput::website($data[$field] ?? null);

            if ($url !== null && $lead->socials()->where('url', $url)->doesntExist()) {
                $lead->socials()->create(['platform' => $platform, 'url' => $url, 'source' => DataSource::Csv]);
            }
        }
    }

    /**
     * @param  array<string, true>  $seen
     * @param  array{domain: string|null, phone: string|null, email: string|null}|null  $keys
     */
    private function seenBefore(array &$seen, ?array $keys): bool
    {
        $found = false;

        foreach (array_filter($keys ?? []) as $type => $value) {
            if ($type === 'domain' && Domain::isShared($value)) {
                continue;
            }

            $found = $found || isset($seen["{$type}:{$value}"]);
            $seen["{$type}:{$value}"] = true;
        }

        return $found;
    }

    /**
     * Accept "US", "us", "USA" or "United States".
     */
    private function countryCode(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (Countries::exists($value)) {
            return strtoupper($value);
        }

        $aliases = ['usa' => 'US', 'uk' => 'GB', 'great britain' => 'GB', 'england' => 'GB', 'uae' => 'AE'];
        $byName = array_change_key_case(array_flip(Countries::options()), CASE_LOWER);

        return $aliases[mb_strtolower($value)] ?? $byName[mb_strtolower($value)] ?? null;
    }
}
