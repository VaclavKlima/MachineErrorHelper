<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\TelescopeServiceProvider;

return array_values(array_filter([
    AppServiceProvider::class,
    AdminPanelProvider::class,
    env('HORIZON_ENABLED', env('APP_ENV') !== 'testing') ? HorizonServiceProvider::class : null,
    env('TELESCOPE_ENABLED', env('APP_ENV') !== 'testing') ? TelescopeServiceProvider::class : null,
]));
