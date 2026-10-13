<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Models\Lead;
use App\Models\User;
use App\Settings\GeneralSettings;
use App\Support\Countries;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class LeadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 3])
            ->components([
                Section::make('Business')
                    ->columnSpan(['lg' => 2])
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Business name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('category')
                            ->placeholder('Plumber')
                            ->maxLength(255)
                            ->datalist(fn (): array => Lead::query()->whereNotNull('category')->distinct()->orderBy('category')->limit(200)->pluck('category')->all()),
                        TextInput::make('phone')
                            ->tel()
                            ->prefixIcon(Heroicon::OutlinedPhone)
                            ->maxLength(50),
                        TextInput::make('website')
                            ->prefixIcon(Heroicon::OutlinedGlobeAlt)
                            ->placeholder('example.com')
                            ->maxLength(2048)
                            ->rule('regex:/^(https?:\/\/)?[^\s\/$.?#]+\.[^\s]+$/i')
                            ->validationMessages(['regex' => 'Enter a web address, such as example.com.'])
                            ->helperText('https:// is added if you leave it out.'),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->prefixIcon(Heroicon::OutlinedEnvelope)
                            ->maxLength(255)
                            ->visibleOn('create')
                            ->helperText('More addresses can be added on the lead page.'),
                    ]),
                Section::make('Owner')
                    ->columnSpan(['lg' => 1])
                    ->schema([
                        Select::make('owner_id')
                            ->label('Assigned to')
                            ->options(fn (): array => User::query()->whereHas('roles')->orderBy('name')->pluck('name', 'id')->all())
                            ->default(fn (): ?int => auth()->id())
                            ->searchable()
                            ->placeholder('Unassigned'),
                    ]),
                Section::make('Address')
                    ->columnSpan(['lg' => 2])
                    ->columns(2)
                    ->schema([
                        TextInput::make('street')->maxLength(255)->columnSpanFull(),
                        TextInput::make('city')->maxLength(255),
                        TextInput::make('region')->label('State / region')->maxLength(255),
                        TextInput::make('postal_code')->label('ZIP / postal code')->maxLength(20),
                        Select::make('country')
                            ->options(Countries::options())
                            ->default(fn (): string => app(GeneralSettings::class)->default_country)
                            ->searchable(),
                    ]),
            ]);
    }
}
