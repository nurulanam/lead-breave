<?php

namespace App\Services\Leads;

use Generator;
use RuntimeException;
use SplFileObject;

/**
 * Streams a CSV file row by row, so large files never load into memory.
 * Handles a UTF-8 byte-order mark and comma, semicolon or tab separators.
 */
class CsvLeadReader
{
    /**
     * Lead fields a column can map to, with the header names that usually mean them.
     *
     * @var array<string, array{label: string, synonyms: list<string>}>
     */
    public const FIELDS = [
        'name' => ['label' => 'Business name', 'synonyms' => ['name', 'business', 'business name', 'company', 'company name', 'title', 'organization', 'organisation']],
        'website' => ['label' => 'Website', 'synonyms' => ['website', 'url', 'web', 'site', 'domain', 'homepage', 'website url']],
        'email' => ['label' => 'Email', 'synonyms' => ['email', 'e-mail', 'email address', 'mail', 'contact email']],
        'phone' => ['label' => 'Phone', 'synonyms' => ['phone', 'telephone', 'tel', 'phone number', 'mobile', 'contact number']],
        'category' => ['label' => 'Category', 'synonyms' => ['category', 'industry', 'type', 'niche', 'business type']],
        'street' => ['label' => 'Street address', 'synonyms' => ['street', 'address', 'address 1', 'street address', 'address line 1']],
        'city' => ['label' => 'City', 'synonyms' => ['city', 'town', 'locality']],
        'region' => ['label' => 'State / region', 'synonyms' => ['state', 'region', 'province', 'county']],
        'postal_code' => ['label' => 'ZIP / postal code', 'synonyms' => ['zip', 'zip code', 'zipcode', 'postal code', 'postcode', 'post code', 'pin code', 'pincode']],
        'country' => ['label' => 'Country', 'synonyms' => ['country', 'country code']],
        'facebook' => ['label' => 'Facebook', 'synonyms' => ['facebook', 'facebook url', 'fb']],
        'instagram' => ['label' => 'Instagram', 'synonyms' => ['instagram', 'instagram url', 'ig']],
        'linkedin' => ['label' => 'LinkedIn', 'synonyms' => ['linkedin', 'linkedin url']],
        'youtube' => ['label' => 'YouTube', 'synonyms' => ['youtube', 'youtube url', 'youtube channel']],
    ];

    /**
     * @return list<string>
     */
    public function headers(string $path): array
    {
        foreach ($this->rows($path, withHeaders: false) as $row) {
            return array_values(array_map(fn (mixed $cell): string => trim((string) $cell), $row['values']));
        }

        throw new RuntimeException('The file is empty.');
    }

    /**
     * Data rows as header => value, with their line number in the file.
     *
     * @return Generator<int, array{line: int, values: array<int|string, string|null>}>
     */
    public function rows(string $path, bool $withHeaders = true, int $offset = 0): Generator
    {
        $file = new SplFileObject($path, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD | SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl($this->separator($path), '"', '');

        $headers = null;
        $dataRow = 0;

        foreach ($file as $index => $row) {
            if (! is_array($row) || $row === [null]) {
                continue;
            }

            if ($index === 0 && isset($row[0]) && is_string($row[0])) {
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]);
            }

            if (! $withHeaders) {
                yield ['line' => (int) $index + 1, 'values' => $row];

                continue;
            }

            if ($headers === null) {
                $headers = array_map(fn (mixed $cell): string => trim((string) $cell), $row);

                continue;
            }

            if ($dataRow++ < $offset) {
                continue;
            }

            $values = [];

            foreach ($headers as $position => $header) {
                $values[$header] = isset($row[$position]) ? (string) $row[$position] : null;
            }

            yield ['line' => (int) $index + 1, 'values' => $values];
        }
    }

    public function count(string $path): int
    {
        $count = 0;

        foreach ($this->rows($path) as $row) {
            $count++;
        }

        return $count;
    }

    /**
     * Pick the most likely column for each field from the header names.
     *
     * @param  list<string>  $headers
     * @return array<string, string|null>
     */
    public function guessMapping(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $header) {
            $normalized[$header] = self::compact($header);
        }

        $mapping = [];

        foreach (self::FIELDS as $field => $definition) {
            $mapping[$field] = null;
            $synonyms = array_map(self::compact(...), $definition['synonyms']);

            foreach ($normalized as $header => $clean) {
                if (in_array($clean, $synonyms, true) && ! in_array($header, $mapping, true)) {
                    $mapping[$field] = $header;

                    break;
                }
            }
        }

        return $mapping;
    }

    /**
     * Letters and digits only, so "E-mail Address" and "email address" compare equal.
     */
    private static function compact(string $value): string
    {
        return (string) preg_replace('/[^a-z0-9]+/', '', mb_strtolower($value));
    }

    private function separator(string $path): string
    {
        $handle = fopen($path, 'r');
        $line = $handle ? (string) fgets($handle) : '';

        if ($handle) {
            fclose($handle);
        }

        $counts = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t")];
        arsort($counts);

        return (string) array_key_first($counts);
    }
}
