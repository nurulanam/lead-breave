<?php

namespace App\Enums;

enum SocialPlatform: string
{
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case LinkedIn = 'linkedin';
    case X = 'x';
    case TikTok = 'tiktok';
    case YouTube = 'youtube';
    case Pinterest = 'pinterest';
    case Yelp = 'yelp';
    case Nextdoor = 'nextdoor';
    case GoogleBusiness = 'google_business';

    public function label(): string
    {
        return match ($this) {
            self::Facebook => 'Facebook',
            self::Instagram => 'Instagram',
            self::LinkedIn => 'LinkedIn',
            self::X => 'X',
            self::TikTok => 'TikTok',
            self::YouTube => 'YouTube',
            self::Pinterest => 'Pinterest',
            self::Yelp => 'Yelp',
            self::Nextdoor => 'Nextdoor',
            self::GoogleBusiness => 'Google Business Profile',
        };
    }
}
