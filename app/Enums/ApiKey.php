<?php

namespace App\Enums;

/**
 * The third-party keys LeadBreave stores. Each value is the property name on
 * ApiSettings.
 */
enum ApiKey: string
{
    case Google = 'google_api_key';
    case Anthropic = 'anthropic_key';

    public function label(): string
    {
        return match ($this) {
            self::Google => 'Google Cloud',
            self::Anthropic => 'Anthropic',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Google => 'One key for lead search, locating areas and website speed tests.',
            self::Anthropic => 'Optional. Lets "Draft with AI" write a first audit note with Claude.',
        };
    }

    public function isRequired(): bool
    {
        return $this === self::Google;
    }

    /**
     * The APIs this key must reach, and what LeadBreave uses each one for.
     *
     * @return array<string, array{label: string, purpose: string}>
     */
    public function services(): array
    {
        return match ($this) {
            self::Google => [
                'places' => ['label' => 'Places API (New)', 'purpose' => 'Finds businesses on Google Maps'],
                'geocoding' => ['label' => 'Geocoding API', 'purpose' => 'Turns a ZIP code or city into an area'],
                'pagespeed' => ['label' => 'PageSpeed Insights API', 'purpose' => 'Measures website speed in audits'],
            ],
            self::Anthropic => [
                'messages' => ['label' => 'Claude API', 'purpose' => 'Drafts audit notes'],
            ],
        };
    }

    /**
     * What a well-formed key looks like, to catch copy-and-paste mistakes.
     */
    public function pattern(): string
    {
        return match ($this) {
            self::Google => '/^AIza[0-9A-Za-z_\-]{35}$/',
            self::Anthropic => '/^sk-ant-[0-9A-Za-z_\-]{20,}$/',
        };
    }

    /**
     * The prefix every key of this kind starts with. Not secret.
     */
    public function publicPrefix(): string
    {
        return match ($this) {
            self::Google => 'AIza',
            self::Anthropic => 'sk-ant-',
        };
    }

    public function formatHint(): string
    {
        return match ($this) {
            self::Google => 'Google API keys start with "AIza" and are 39 characters long.',
            self::Anthropic => 'Anthropic API keys start with "sk-ant-".',
        };
    }

    public function placeholder(): string
    {
        return match ($this) {
            self::Google => 'AIza…',
            self::Anthropic => 'sk-ant-…',
        };
    }

    /**
     * @return list<array{text: string, url?: string}>
     */
    public function setupSteps(): array
    {
        return match ($this) {
            self::Google => [
                ['text' => 'Open Google Cloud and pick (or create) a project with billing enabled.', 'url' => 'https://console.cloud.google.com/billing'],
                ['text' => 'Enable Places API (New).', 'url' => 'https://console.cloud.google.com/apis/library/places.googleapis.com'],
                ['text' => 'Enable Geocoding API.', 'url' => 'https://console.cloud.google.com/apis/library/geocoding-backend.googleapis.com'],
                ['text' => 'Enable PageSpeed Insights API.', 'url' => 'https://console.cloud.google.com/apis/library/pagespeedonline.googleapis.com'],
                ['text' => 'Create an API key under Credentials. Restrict it to those three APIs, and to this server\'s IP address rather than to websites.', 'url' => 'https://console.cloud.google.com/apis/credentials'],
                ['text' => 'Paste the key here. It is tested as soon as you save it.'],
            ],
            self::Anthropic => [
                ['text' => 'Sign in to the Claude Console and open API keys.', 'url' => 'https://console.anthropic.com/settings/keys'],
                ['text' => 'Create a key for LeadBreave and paste it here. It is tested as soon as you save it.'],
            ],
        };
    }
}
