<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Nightly SAM.gov load for the previous calendar day (app timezone). Dispatches to the queue — keep a worker running.
Schedule::command('fbo:load --yesterday')
    ->dailyAt('02:00')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/fbo-feed.log'));
