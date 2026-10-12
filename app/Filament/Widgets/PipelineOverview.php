<?php

namespace App\Filament\Widgets;

use App\Enums\Permission;
use Filament\Widgets\Widget;

class PipelineOverview extends Widget
{
    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.pipeline-overview';

    /**
     * Share the row with the setup checklist when it is visible.
     *
     * @return int|string|array<string, int|string|null>
     */
    public function getColumnSpan(): int|string|array
    {
        return auth()->user()?->can(Permission::ManageSettings->value) ? ['lg' => 2] : 'full';
    }

    /**
     * The workflow stages. Counts are wired up as each phase adds its tables.
     *
     * @return list<array{key: string, label: string, description: string, count: int}>
     */
    public static function stages(): array
    {
        return [
            ['key' => 'found', 'label' => 'Found', 'description' => 'From Google Maps', 'count' => 0],
            ['key' => 'enriched', 'label' => 'Enriched', 'description' => 'Email or socials found', 'count' => 0],
            ['key' => 'audited', 'label' => 'Audited', 'description' => 'Issues confirmed', 'count' => 0],
            ['key' => 'contacted', 'label' => 'Contacted', 'description' => 'First email sent', 'count' => 0],
            ['key' => 'followed_up', 'label' => 'Followed up', 'description' => 'After 2 business days', 'count' => 0],
            ['key' => 'replied', 'label' => 'Replied', 'description' => 'Conversation open', 'count' => 0],
            ['key' => 'meeting', 'label' => 'Meeting booked', 'description' => 'Call scheduled', 'count' => 0],
        ];
    }

    /**
     * @return array{stages: list<array{key: string, label: string, description: string, count: int, percent: int|null}>, total: int}
     */
    protected function getViewData(): array
    {
        $stages = self::stages();
        $total = $stages[0]['count'];

        return [
            'stages' => array_map(fn (array $stage): array => [
                ...$stage,
                'percent' => $total > 0 ? (int) round($stage['count'] / $total * 100) : null,
            ], $stages),
            'total' => $total,
        ];
    }
}
