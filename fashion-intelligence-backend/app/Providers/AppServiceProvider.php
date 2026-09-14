<?php

namespace App\Providers;

use App\Services\PythonEngineClient;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PythonEngineClient::class);
    }

    public function boot(): void
    {
        Paginator::defaultView('livewire.pagination');
    }
}
