<?php

namespace App\Services\Leads;

use App\Support\Phone;

/**
 * Tidies lead details typed by hand or read from a CSV, so they match what
 * search produces and dedupe works the same way.
 */
class LeadInput
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalize(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = trim($value) === '' ? null : trim($value);
            }
        }

        if (array_key_exists('website', $data)) {
            $data['website'] = self::website($data['website']);
        }

        if (array_key_exists('country', $data) && is_string($data['country'])) {
            $data['country'] = strtoupper($data['country']);
        }

        if (array_key_exists('phone', $data)) {
            $data['phone_e164'] = Phone::toE164(null, $data['phone'], $data['country'] ?? 'US');
        }

        if (array_key_exists('email', $data) && is_string($data['email'])) {
            $data['email'] = mb_strtolower($data['email']);
        }

        return $data;
    }

    public static function website(mixed $website): ?string
    {
        if (! is_string($website) || trim($website) === '') {
            return null;
        }

        $website = trim($website);

        return preg_match('#^https?://#i', $website) === 1 ? $website : 'https://'.$website;
    }
}
