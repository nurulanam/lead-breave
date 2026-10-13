<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Dashboard;
use Filament\Enums\UserMenuPosition;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->brandName('LeadBreave')
            ->brandLogo(fn () => view('filament.brand.logo'))
            ->brandLogoHeight('1.75rem')
            ->favicon(asset('favicon.svg'))
            ->font('Plus Jakarta Sans')
            ->colors([
                'primary' => Color::Violet,
                'gray' => Color::Slate,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Red,
                'info' => Color::Sky,
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->maxContentWidth(Width::Full)
            ->topbar(false)
            ->userMenu(position: UserMenuPosition::Sidebar)
            ->globalSearch(false)
            ->sidebarWidth('15rem')
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups(['Prospecting', 'Admin', 'Settings'])
            ->renderHook(PanelsRenderHook::BODY_START, fn (): View => view('filament.aurora'))
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => Blade::render("@vite('resources/js/filament/admin.js')"))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
