<?php

namespace App\Filament\Pages;

use App\Enums\ApiKey;
use App\Enums\Permission;
use App\Models\ApiCall;
use App\Models\User;
use App\Services\ApiKeys\ApiKeyTester;
use App\Services\Search\CostGuard;
use App\Settings\ApiSettings;
use App\Settings\SearchSettings;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * One card per service. Keys go in through a modal, are tested straight away,
 * and are never sent back to the browser: the page only shows a masked form.
 */
class ManageApiKeys extends Page
{
    protected string $view = 'filament.pages.manage-api-keys';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'API keys';

    protected static ?string $title = 'API keys';

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::ManageSettings->value) ?? false;
    }

    public function getSubheading(): string
    {
        return 'Connect the services LeadBreave uses. Each key is tested as soon as you save it.';
    }

    /**
     * @return list<array{key: ApiKey, masked: string|null, updatedAt: Carbon|null, updatedBy: string|null, checkedAt: Carbon|null, status: string, services: list<array{label: string, purpose: string, ok: bool|null, message: string|null}>}>
     */
    public function integrations(): array
    {
        $settings = app(ApiSettings::class);

        return array_map(function (ApiKey $key) use ($settings): array {
            $check = $settings->lastCheck($key);
            $results = $check['services'] ?? [];
            $services = [];

            foreach ($key->services() as $name => $service) {
                $services[] = [
                    ...$service,
                    'ok' => isset($results[$name]) ? (bool) $results[$name]['ok'] : null,
                    'message' => $results[$name]['message'] ?? null,
                ];
            }

            return [
                'key' => $key,
                'masked' => $settings->masked($key),
                'updatedAt' => $settings->updatedAt($key),
                'updatedBy' => $settings->updatedBy($key),
                'checkedAt' => $check ? Carbon::parse($check['checked_at']) : null,
                'status' => $this->status($settings->has($key->value), $check === null ? null : array_column($results, 'ok')),
                'services' => $services,
            ];
        }, ApiKey::cases());
    }

    /**
     * Google spend this month, from the API call ledger.
     *
     * @return array{places: int, geocoding: int, cap: int, cost: float}
     */
    public function googleUsage(): array
    {
        $settings = app(SearchSettings::class);
        $places = app(CostGuard::class)->placesRequestsThisMonth();
        $geocoding = (int) ApiCall::query()->forProvider('google_geocoding')->since(now()->startOfMonth())->sum('cost_units');

        return [
            'places' => $places,
            'geocoding' => $geocoding,
            'cap' => $settings->monthly_request_cap,
            'cost' => $settings->estimateCost($places, $geocoding),
        ];
    }

    public function replaceKeyAction(): Action
    {
        return Action::make('replaceKey')
            ->label(fn (array $arguments): string => $this->hasKey($arguments) ? 'Replace key' : 'Add key')
            ->icon(fn (array $arguments): Heroicon => $this->hasKey($arguments) ? Heroicon::OutlinedArrowPath : Heroicon::OutlinedPlus)
            ->color(fn (array $arguments): string => $this->hasKey($arguments) ? 'gray' : 'primary')
            ->size('sm')
            ->authorize(fn (): bool => static::canAccess())
            ->modalHeading(fn (array $arguments): string => ($this->hasKey($arguments) ? 'Replace your ' : 'Add your ').$this->apiKey($arguments)->label().' key')
            ->modalDescription(fn (array $arguments): string => $this->hasKey($arguments)
                ? 'The new key takes over straight away and is tested once saved. The old key is deleted.'
                : 'The key is encrypted before it is stored and tested once saved.')
            ->modalWidth('lg')
            ->schema(fn (array $arguments): array => [
                TextInput::make('value')
                    ->label('API key')
                    ->password()
                    ->revealable()
                    ->required()
                    ->autocomplete('off')
                    ->placeholder($this->apiKey($arguments)->placeholder())
                    ->helperText($this->apiKey($arguments)->formatHint())
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($arguments): void {
                        if (preg_match($this->apiKey($arguments)->pattern(), self::clean((string) $value)) !== 1) {
                            $fail('This does not look like '.($this->apiKey($arguments) === ApiKey::Anthropic ? 'an ' : 'a ').$this->apiKey($arguments)->label().' API key. Check you copied the whole key, not a project ID or secret.');
                        }
                    }),
            ])
            ->modalSubmitActionLabel('Save and test')
            ->action(function (array $data, array $arguments, ApiSettings $settings): void {
                $key = $this->apiKey($arguments);

                /** @var User|null $user */
                $user = auth()->user();
                $settings->replace($key, self::clean((string) $data['value']), $user);

                $this->runTest($key);
            });
    }

    public function testKeyAction(): Action
    {
        return Action::make('testKey')
            ->label('Test connection')
            ->icon(Heroicon::OutlinedSignal)
            ->color('gray')
            ->size('sm')
            ->authorize(fn (): bool => static::canAccess())
            ->visible(fn (array $arguments): bool => $this->hasKey($arguments))
            ->action(fn (array $arguments) => $this->runTest($this->apiKey($arguments)));
    }

    public function removeKeyAction(): Action
    {
        return Action::make('removeKey')
            ->label('Remove')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->link()
            ->size('sm')
            ->authorize(fn (): bool => static::canAccess())
            ->visible(fn (array $arguments): bool => $this->hasKey($arguments))
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => 'Remove the '.$this->apiKey($arguments)->label().' key?')
            ->modalDescription(fn (array $arguments): string => $this->apiKey($arguments)->isRequired()
                ? 'Searches and speed tests stop working until a new key is added. The key is deleted, not just hidden.'
                : '"Draft with AI" stops working until a new key is added. The key is deleted, not just hidden.')
            ->modalSubmitActionLabel('Remove key')
            ->action(function (array $arguments, ApiSettings $settings): void {
                $key = $this->apiKey($arguments);

                /** @var User|null $user */
                $user = auth()->user();
                $settings->replace($key, null, $user);

                Notification::make()->title($key->label().' key removed')->success()->send();
            });
    }

    /**
     * Strip the whitespace and quotes that often come along when a key is copied.
     */
    public static function clean(string $value): string
    {
        return trim($value, " \t\n\r\0\x0B\"'`");
    }

    private function runTest(ApiKey $key): void
    {
        $settings = app(ApiSettings::class);
        $value = $settings->value($key);

        if ($value === null) {
            return;
        }

        $results = app(ApiKeyTester::class)->test($key, $value);
        $settings->recordCheck($key, $results);

        $failures = array_filter($results, fn (array $result): bool => ! $result['ok']);

        $notification = match (true) {
            $failures === [] => Notification::make()->title($key->label().' is connected')->body('Every API responded.')->success(),
            count($failures) === count($results) => Notification::make()->title($key->label().' is not working')->body(reset($failures)['message'])->danger(),
            default => Notification::make()->title($key->label().' is partly working')->body(reset($failures)['message'])->warning(),
        };

        $notification->persistent()->send();
    }

    /**
     * @param  list<bool>|null  $results
     */
    private function status(bool $hasKey, ?array $results): string
    {
        return match (true) {
            ! $hasKey => 'missing',
            $results === null => 'untested',
            ! in_array(false, $results, true) => 'connected',
            in_array(true, $results, true) => 'partial',
            default => 'failing',
        };
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function apiKey(array $arguments): ApiKey
    {
        return ApiKey::tryFrom((string) ($arguments['key'] ?? '')) ?? abort(404);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function hasKey(array $arguments): bool
    {
        return app(ApiSettings::class)->has($this->apiKey($arguments)->value);
    }
}
