<?php

use App\Domain\Analytics\Jobs\AggregateAnalyticsForDate;
use App\Domain\Campaigns\Actions\RefreshCampaignDates;
use App\Domain\Devices\Jobs\MarkOfflineDevices;
use App\Domain\Devices\Jobs\PruneDeviceHeartbeats;
use App\Domain\QuickPlay\Jobs\FailExpiredQuickPlays;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled network maintenance
|--------------------------------------------------------------------------
| Analytics are never computed from raw events on page load. Instead the
| scheduler rolls events into daily aggregate tables and keeps device health
| state fresh.
*/

// Roll yesterday's proof-of-play events into the aggregate tables.
Schedule::job(new AggregateAnalyticsForDate(today()->subDay()->toDateString()))
    ->dailyAt('01:15')
    ->withoutOverlapping()
    ->onOneServer();

// Keep today's aggregates warm so the dashboard is never cold.
Schedule::job(new AggregateAnalyticsForDate(today()->toDateString()))
    ->hourly()
    ->withoutOverlapping();

// Flag devices that stopped reporting and open alerts.
Schedule::job(new MarkOfflineDevices)
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Enforce heartbeat retention so the raw table stays small.
Schedule::job(new PruneDeviceHeartbeats)
    ->dailyAt('03:30')
    ->withoutOverlapping();

// Close quick plays whose devices never confirmed delivery.
Schedule::job(new FailExpiredQuickPlays)
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('queue:prune-failed --hours=48')->daily();
Schedule::command('model:prune')->daily();

Schedule::call(fn () => app(RefreshCampaignDates::class)->handle())->everyMinute()->name('campaign-dates')->withoutOverlapping();
