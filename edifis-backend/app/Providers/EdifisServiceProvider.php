<?php

namespace App\Providers;

use App\Domain\Tenancy\Services\ModeGate;
use App\Domain\Tenancy\Services\TenantContext;
use App\Support\ClockService;
use Illuminate\Support\ServiceProvider;

class EdifisServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModeGate::class);
        $this->app->singleton(TenantContext::class);
        $this->app->singleton(ClockService::class);
    }

    public function boot(): void
    {
        //
    }
}
