<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class StaffPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('staff')
            ->path('staff')
            ->login()
            ->authGuard('web')
            ->brandName('myEDIFIS')
            ->brandLogo(asset('brand/logo.png'))
            ->darkModeBrandLogo(asset('brand/logo-white.png'))
            ->brandLogoHeight('2.2rem')
            ->favicon(asset('favicon.ico'))
            ->colors([
                'primary' => Color::hex('#2563EB'),
                'gray' => Color::Slate,
                'info' => Color::Blue,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Red,
            ])
            ->darkMode(false)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    public function register(): void
    {
        parent::register();

        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => <<<'HTML'
            <style>
              .fi-simple-layout{background:
                radial-gradient(1200px 480px at 80% -10%, rgba(56,189,248,.35), transparent 60%),
                linear-gradient(135deg,#0F2350,#1E40AF 50%,#2563EB)!important;}
              .fi-simple-main{background:rgba(255,255,255,.95)!important;border-radius:20px!important;box-shadow:0 30px 80px -30px rgba(15,35,80,.65)!important;padding:2.5rem!important}
              .fi-logo img{height:2.4rem!important}
              .fi-input-wrp{box-shadow:0 2px 10px -4px rgba(15,35,80,.12)}
              .fi-btn{font-weight:700!important;border-radius:11px!important}
              .fi-btn.fi-color-primary{background:linear-gradient(180deg,#2563EB,#1D4ED8)!important;box-shadow:0 10px 28px -8px rgba(37,99,235,.55)!important}
              .fi-btn.fi-color-primary:hover{transform:translateY(-2px);box-shadow:0 16px 36px -8px rgba(37,99,235,.65)!important}
              .fi-simple-main, .fi-simple-main h1, .fi-simple-main h2,
              .fi-simple-main label, .fi-simple-main .fi-fo-field-wrp-label,
              .fi-simple-main .fi-checkbox-input-label, .fi-simple-main p, .fi-simple-main span{
                color:#0F2350 !important;
              }
              .fi-simple-main .fi-input{ color:#0B1220 !important; background:#fff !important; }
              .fi-simple-main .fi-input::placeholder{ color:#94a3b8 !important; }
              .fi-simple-main a{ color:#2563EB !important; }
              .fi-simple-main .fi-btn.fi-color-primary, .fi-simple-main .fi-btn.fi-color-primary *{ color:#fff !important; }
            </style>
            HTML,
        );
    }
}
