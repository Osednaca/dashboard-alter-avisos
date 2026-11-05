<?php

use App\Http\Controllers\Business\BusinessSwitchController;
use App\Http\Controllers\Business\ContentController;
use App\Http\Controllers\Business\DashboardController;
use App\Http\Controllers\Business\MediaController;
use App\Http\Controllers\Business\PlaylistController;
use App\Http\Controllers\Business\PlaylistItemController;
use App\Http\Controllers\Business\PreviewController;
use App\Http\Controllers\Business\QuickPlayController;
use App\Http\Controllers\Business\ReportController;
use App\Http\Controllers\Business\ScheduleController;
use App\Http\Controllers\Business\ScreenController;
use App\Http\Controllers\Business\SearchController;
use App\Http\Controllers\Business\SettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Business dashboard routes
|--------------------------------------------------------------------------
| Every route runs behind `business.context`, which resolves the authenticated
| business user's business. Controllers scope all queries to that business and
| verify ownership, so an ID from another business always results in a 404.
*/

Route::get('dashboard', [DashboardController::class, 'index'])
    ->middleware('permission:business.dashboard.view')
    ->name('business.dashboard');

Route::post('switch', [BusinessSwitchController::class, 'update'])->name('business.switch');
Route::get('search', SearchController::class)->name('business.search');

Route::middleware('permission:business.devices.view')->group(function () {
    Route::get('quick-play', [QuickPlayController::class, 'index'])->name('business.quick-play.index');
    Route::get('quick-play/create', [QuickPlayController::class, 'create'])
        ->middleware('permission:business.playlists.manage')->name('business.quick-play.create');
    Route::post('quick-play', [QuickPlayController::class, 'send'])
        ->middleware('permission:business.playlists.manage')->name('business.quick-play.store');
    Route::get('quick-play/{quickPlay}', [QuickPlayController::class, 'show'])->name('business.quick-play.show');
    Route::post('quick-play/{quickPlay}/retry', [QuickPlayController::class, 'retry'])
        ->middleware('permission:business.playlists.manage')->name('business.quick-play.retry');
    Route::delete('quick-play/{quickPlay}', [QuickPlayController::class, 'destroy'])
        ->middleware('permission:business.playlists.manage')->name('business.quick-play.destroy');
});

// Content overview
Route::get('content', [ContentController::class, 'index'])
    ->middleware('permission:business.media.view')
    ->name('business.content');

// Media library
Route::get('library', [MediaController::class, 'index'])
    ->middleware('permission:business.media.view')
    ->name('business.library.index');
Route::post('library', [MediaController::class, 'store'])
    ->middleware('permission:business.media.upload')
    ->name('business.library.store');
Route::put('library/{media}', [MediaController::class, 'update'])
    ->middleware('permission:business.media.upload')
    ->name('business.library.update');
Route::delete('library/{media}', [MediaController::class, 'destroy'])
    ->middleware('permission:business.media.delete')
    ->name('business.library.destroy');

// Playlists
Route::get('playlists', [PlaylistController::class, 'index'])
    ->middleware('permission:business.playlists.view')
    ->name('business.playlists.index');
Route::post('playlists', [PlaylistController::class, 'store'])
    ->middleware('permission:business.playlists.manage')
    ->name('business.playlists.store');
Route::get('playlists/{playlist}', [PlaylistController::class, 'show'])
    ->middleware('permission:business.playlists.view')
    ->name('business.playlists.show');
Route::put('playlists/{playlist}', [PlaylistController::class, 'update'])
    ->middleware('permission:business.playlists.manage')
    ->name('business.playlists.update');
Route::delete('playlists/{playlist}', [PlaylistController::class, 'destroy'])
    ->middleware('permission:business.playlists.manage')
    ->name('business.playlists.destroy');
Route::post('playlists/{playlist}/duplicate', [PlaylistController::class, 'duplicate'])
    ->middleware('permission:business.playlists.manage')
    ->name('business.playlists.duplicate');
Route::post('playlists/{playlist}/items', [PlaylistItemController::class, 'store'])
    ->middleware('permission:business.playlists.manage')
    ->name('business.playlists.items.store');
Route::put('playlists/{playlist}/items/reorder', [PlaylistItemController::class, 'reorder'])
    ->middleware('permission:business.playlists.manage')
    ->name('business.playlists.items.reorder');
Route::put('playlists/{playlist}/items/{item}', [PlaylistItemController::class, 'update'])
    ->middleware('permission:business.playlists.manage')
    ->name('business.playlists.items.update');
Route::delete('playlists/{playlist}/items/{item}', [PlaylistItemController::class, 'destroy'])
    ->middleware('permission:business.playlists.manage')
    ->name('business.playlists.items.destroy');

// Scheduling
Route::get('schedule/media', [ScheduleController::class, 'media'])
    ->middleware(['permission:business.schedules.view', 'permission:business.media.view'])
    ->name('business.schedule.media');
Route::get('schedule/media-status', [ScheduleController::class, 'mediaStatus'])
    ->middleware(['permission:business.schedules.view', 'permission:business.media.view'])
    ->name('business.schedule.media-status');
Route::get('schedule', [ScheduleController::class, 'index'])
    ->middleware('permission:business.schedules.view')
    ->name('business.schedule.index');
Route::post('schedule', [ScheduleController::class, 'store'])
    ->middleware('permission:business.schedules.manage')
    ->name('business.schedule.store');
Route::put('schedule/{schedule}', [ScheduleController::class, 'update'])
    ->middleware('permission:business.schedules.manage')
    ->name('business.schedule.update');
Route::delete('schedule/{schedule}', [ScheduleController::class, 'destroy'])
    ->middleware('permission:business.schedules.manage')
    ->name('business.schedule.destroy');

// Screens
Route::get('screens', [ScreenController::class, 'index'])
    ->middleware('permission:business.devices.view')
    ->name('business.screens.index');
Route::get('screens/{device}', [ScreenController::class, 'show'])
    ->middleware('permission:business.devices.view')
    ->name('business.screens.show');
Route::post('screens/{device}/sync', [ScreenController::class, 'sync'])
    ->middleware('permission:business.devices.sync')
    ->name('business.screens.sync');

// Live preview
Route::get('preview', [PreviewController::class, 'index'])
    ->middleware('permission:business.devices.view')
    ->name('business.preview');

// Reports
Route::get('reports', [ReportController::class, 'index'])
    ->middleware('permission:business.reports.view')
    ->name('business.reports');

// Settings
Route::get('settings', [SettingsController::class, 'index'])
    ->middleware('permission:business.settings.view')
    ->name('business.settings');
Route::put('settings', [SettingsController::class, 'update'])
    ->middleware('permission:business.settings.manage')
    ->name('business.settings.update');
Route::post('settings/locations', [SettingsController::class, 'storeLocation'])
    ->middleware('permission:business.settings.manage')
    ->name('business.settings.locations.store');
Route::put('settings/locations/{location}', [SettingsController::class, 'updateLocation'])
    ->middleware('permission:business.settings.manage')
    ->name('business.settings.locations.update');
Route::delete('settings/locations/{location}', [SettingsController::class, 'destroyLocation'])
    ->middleware('permission:business.settings.manage')
    ->name('business.settings.locations.destroy');
