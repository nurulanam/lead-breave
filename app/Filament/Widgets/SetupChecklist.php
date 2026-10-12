<?php

namespace App\Filament\Widgets;

use App\Enums\Permission;
use App\Filament\Pages\ManageApiKeys;
use App\Filament\Pages\ManageGeneralSettings;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Settings\ApiSettings;
use App\Settings\GeneralSettings;
use Filament\Widgets\Widget;

class SetupChecklist extends Widget
{
    protected static ?int $sort = 3;

    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.setup-checklist';

    protected int|string|array $columnSpan = ['lg' => 1];

    public static function canView(): bool
    {
        return auth()->user()?->can(Permission::ManageSettings->value) ?? false;
    }

    /**
     * @return list<array{label: string, hint: string, done: bool, url: string}>
     */
    public static function items(): array
    {
        $api = app(ApiSettings::class);
        $general = app(GeneralSettings::class);

        return [
            [
                'label' => 'Connect Google Places',
                'hint' => 'Needed to search Google Maps.',
                'done' => $api->has('google_places_key'),
                'url' => ManageApiKeys::getUrl(),
            ],
            [
                'label' => 'Connect PageSpeed Insights',
                'hint' => 'Powers the speed audit button.',
                'done' => $api->has('pagespeed_key'),
                'url' => ManageApiKeys::getUrl(),
            ],
            [
                'label' => 'Set your sender identity',
                'hint' => 'The name and email leads see.',
                'done' => filled($general->sender_name) && filled($general->sender_email),
                'url' => ManageGeneralSettings::getUrl(),
            ],
            [
                'label' => 'Add a postal address',
                'hint' => 'Required in every outreach footer.',
                'done' => filled($general->postal_address),
                'url' => ManageGeneralSettings::getUrl(),
            ],
            [
                'label' => 'Add your booking link',
                'hint' => 'Used when proposing a meeting.',
                'done' => filled($general->booking_url),
                'url' => ManageGeneralSettings::getUrl(),
            ],
            [
                'label' => 'Invite your team',
                'hint' => 'Add researchers and senders.',
                'done' => User::query()->count() > 1,
                'url' => UserResource::getUrl('create'),
            ],
        ];
    }

    /**
     * @return array{done: int, total: int}
     */
    public static function progress(): array
    {
        $items = self::items();

        return [
            'done' => count(array_filter($items, fn (array $item): bool => $item['done'])),
            'total' => count($items),
        ];
    }

    /**
     * @return array{items: list<array{label: string, hint: string, done: bool, url: string}>, done: int, total: int}
     */
    protected function getViewData(): array
    {
        return ['items' => self::items(), ...self::progress()];
    }
}
