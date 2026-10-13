<?php

namespace App\Enums;

/**
 * The parts of a website check. Each type keeps its own row, so re-running
 * one never overwrites another.
 */
enum WebsiteCheckType: string
{
    case Crawl = 'crawl';
    case Ssl = 'ssl';
    case Seo = 'seo';
    case Tech = 'tech';

    public function label(): string
    {
        return match ($this) {
            self::Crawl => 'Reachability and contacts',
            self::Ssl => 'Security (HTTPS)',
            self::Seo => 'On-page SEO',
            self::Tech => 'Technology',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Crawl => 'heroicon-o-signal',
            self::Ssl => 'heroicon-o-lock-closed',
            self::Seo => 'heroicon-o-magnifying-glass-circle',
            self::Tech => 'heroicon-o-cpu-chip',
        };
    }
}
