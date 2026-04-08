<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\CustomMailServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\HelperServiceProvider;
use App\Providers\RouteServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    // App\Providers\BroadcastServiceProvider::class,
    EventServiceProvider::class,
    RouteServiceProvider::class,
    HelperServiceProvider::class,
    CustomMailServiceProvider::class,
];
