<?php

namespace App\Filament\Widgets;

use App\Enums\Permission;
use App\Filament\Pages\ManageApiKeys;
use App\Models\User;
use App\Settings\GeneralSettings;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

class CommandCenter extends Widget
{
    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.command-center';

    /**
     * @return list<array{label: string, value: string, caption: string, icon: string, tone: string}>
     */
    public static function kpis(): array
    {
        $counts = collect(PipelineOverview::stages())->pluck('count', 'key');
        $contacted = (int) ($counts['contacted'] ?? 0);

        return [
            ['label' => 'Leads', 'value' => number_format((int) ($counts['found'] ?? 0)), 'caption' => 'Found on Google Maps', 'icon' => 'heroicon-m-map-pin', 'tone' => 'cyan'],
            ['label' => 'Contacted', 'value' => number_format($contacted), 'caption' => 'First email sent', 'icon' => 'heroicon-m-paper-airplane', 'tone' => 'violet'],
            ['label' => 'Reply rate', 'value' => $contacted > 0 ? round(($counts['replied'] ?? 0) / $contacted * 100).'%' : '—', 'caption' => 'Replies per contacted lead', 'icon' => 'heroicon-m-chat-bubble-left-right', 'tone' => 'fuchsia'],
            ['label' => 'Meetings', 'value' => number_format((int) ($counts['meeting'] ?? 0)), 'caption' => 'Calls on the calendar', 'icon' => 'heroicon-m-calendar-days', 'tone' => 'emerald'],
        ];
    }

    /**
     * @return array{greeting: string, name: string, date: string, kpis: list<array{label: string, value: string, caption: string, icon: string, tone: string}>, setup: array{done: int, total: int}|null, setupUrl: string}
     */
    protected function getViewData(): array
    {
        /** @var User $user */
        $user = auth()->user();
        $now = Carbon::now(app(GeneralSettings::class)->timezone);

        return [
            'greeting' => match (true) {
                $now->hour < 12 => 'Good morning',
                $now->hour < 18 => 'Good afternoon',
                default => 'Good evening',
            },
            'name' => str($user->name)->before(' ')->toString(),
            'date' => $now->format('l, F j'),
            'kpis' => self::kpis(),
            'setup' => $user->can(Permission::ManageSettings->value) ? SetupChecklist::progress() : null,
            'setupUrl' => ManageApiKeys::getUrl(),
        ];
    }
}
