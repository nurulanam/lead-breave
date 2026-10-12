<?php

namespace App\Filament\Widgets;

use App\Enums\Permission;
use Filament\Widgets\Widget;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Throwable;

class SystemStatus extends Widget
{
    protected static ?int $sort = 4;

    protected string $view = 'filament.widgets.system-status';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can(Permission::ViewSystemTools->value) ?? false;
    }

    /**
     * @return list<array{label: string, value: string, state: 'ok'|'warning'|'danger', hint: string|null}>
     */
    public function checks(): array
    {
        $queue = (string) config('queue.default');
        $mailer = (string) config('mail.default');

        return [
            [
                'label' => 'Queue driver',
                'value' => $queue,
                'state' => $queue === 'redis' ? 'ok' : 'warning',
                'hint' => $queue === 'redis' ? null : 'Horizon needs the redis driver.',
            ],
            $this->horizonCheck($queue),
            [
                'label' => 'Cache store',
                'value' => (string) config('cache.default'),
                'state' => config('cache.default') === 'redis' ? 'ok' : 'warning',
                'hint' => config('cache.default') === 'redis' ? null : 'Locks and rate limits work best on Redis.',
            ],
            [
                'label' => 'Mailer',
                'value' => $mailer,
                'state' => in_array($mailer, ['log', 'array'], true) ? 'warning' : 'ok',
                'hint' => in_array($mailer, ['log', 'array'], true) ? 'Emails are written to the log, not sent.' : null,
            ],
        ];
    }

    /**
     * @return array{label: string, value: string, state: 'ok'|'warning'|'danger', hint: string|null}
     */
    private function horizonCheck(string $queue): array
    {
        if ($queue !== 'redis') {
            return ['label' => 'Horizon', 'value' => 'Not in use', 'state' => 'warning', 'hint' => null];
        }

        try {
            $running = count(app(MasterSupervisorRepository::class)->all()) > 0;
        } catch (Throwable) {
            return ['label' => 'Horizon', 'value' => 'Redis unreachable', 'state' => 'danger', 'hint' => 'Check REDIS_HOST and that Redis is running.'];
        }

        return $running
            ? ['label' => 'Horizon', 'value' => 'Running', 'state' => 'ok', 'hint' => null]
            : ['label' => 'Horizon', 'value' => 'Stopped', 'state' => 'danger', 'hint' => 'Start it with php artisan horizon.'];
    }
}
