<?php

namespace App\Services\Website;

/**
 * Minimal robots.txt reader: the Disallow rules for our bot, or for every
 * bot. Ported from LeadMap's Robots.
 */
class Robots
{
    /** @var list<string> */
    private array $disallow = [];

    public function __construct(string $body = '')
    {
        $this->parse($body);
    }

    public function allows(string $path): bool
    {
        $path = $path === '' ? '/' : $path;

        foreach ($this->disallow as $prefix) {
            if ($prefix === '/' || str_starts_with($path, $prefix)) {
                return false;
            }
        }

        return true;
    }

    private function parse(string $body): void
    {
        $groups = [];
        $agents = [];
        $rules = [];
        $inRules = false;

        foreach (preg_split('/\r\n|\r|\n/', $body) ?: [] as $line) {
            $line = trim((string) preg_replace('/#.*$/', '', $line));

            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }

            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);

            if ($field === 'user-agent') {
                if ($inRules) {
                    foreach ($agents as $agent) {
                        $groups[$agent] = [...($groups[$agent] ?? []), ...$rules];
                    }

                    [$agents, $rules, $inRules] = [[], [], false];
                }

                $agents[] = strtolower($value);

                continue;
            }

            if ($field === 'disallow') {
                $inRules = true;

                if ($value !== '') {
                    $rules[] = $value;
                }
            }
        }

        foreach ($agents as $agent) {
            $groups[$agent] = [...($groups[$agent] ?? []), ...$rules];
        }

        $this->disallow = $groups['leadbreavebot'] ?? $groups['*'] ?? [];
    }
}
