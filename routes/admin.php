<?php

use App\Http\Controllers\Admin\AdvertiserController;
use App\Http\Controllers\Admin\AlertController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\AndroidUpdateController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\BusinessController;
use App\Http\Controllers\Admin\CampaignController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeviceController;
use App\Http\Controllers\Admin\GlobalScreenPinController;
use App\Http\Controllers\Admin\LiveStreamController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\QuickPlayController;
use App\Http\Controllers\Admin\SearchController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin dashboard routes (authenticated, permission gated)
|--------------------------------------------------------------------------
| Permissions are enforced in middleware AND in policies. The frontend only
| mirrors permissions for UX; it is never the source of truth.
*/

Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('search', SearchController::class)->name('search');

// Businesses
Route::get('businesses', [BusinessController::class, 'index'])->middleware('permission:businesses.view')->name('businesses.index');
Route::post('businesses', [BusinessController::class, 'store'])->middleware('permission:businesses.create')->name('businesses.store');
Route::get('businesses/{business}', [BusinessController::class, 'show'])->middleware('permission:businesses.view')->name('businesses.show');
Route::put('businesses/{business}', [BusinessController::class, 'update'])->middleware('permission:businesses.edit')->name('businesses.update');
Route::delete('businesses/{business}', [BusinessController::class, 'destroy'])->middleware('permission:businesses.delete')->name('businesses.destroy');

// Locations
Route::get('locations', [LocationController::class, 'index'])->middleware('permission:locations.view')->name('locations.index');
Route::post('locations', [LocationController::class, 'store'])->middleware('permission:locations.manage')->name('locations.store');
Route::get('locations/{location}', [LocationController::class, 'show'])->middleware('permission:locations.view')->name('locations.show');
Route::put('locations/{location}', [LocationController::class, 'update'])->middleware('permission:locations.manage')->name('locations.update');
Route::delete('locations/{location}', [LocationController::class, 'destroy'])->middleware('permission:locations.manage')->name('locations.destroy');

// Devices / screens
Route::get('devices/global-pin', [GlobalScreenPinController::class, 'show'])->middleware('permission:devices.manage')->name('devices.global-pin');
Route::put('devices/global-pin', [GlobalScreenPinController::class, 'update'])->middleware('permission:devices.manage')->name('devices.global-pin.update');
Route::get('devices/activations', [DeviceController::class, 'activations'])->middleware('permission:devices.view')->name('devices.activations');
Route::post('activations/{activation}/assign', [DeviceController::class, 'assignActivation'])->middleware('permission:devices.manage')->name('activations.assign');
Route::post('activations/{activation}/revoke', [DeviceController::class, 'revokeActivation'])->middleware('permission:devices.manage')->name('activations.revoke');
Route::get('devices', [DeviceController::class, 'index'])->middleware('permission:devices.view')->name('devices.index');
Route::get('devices/{device}', [DeviceController::class, 'show'])->middleware('permission:devices.view')->name('devices.show');
Route::post('devices/{device}/commands', [DeviceController::class, 'command'])->middleware('permission:devices.commands')->name('devices.commands');
Route::post('devices/{device}/sync', [DeviceController::class, 'sync'])->middleware('permission:devices.manage')->name('devices.sync');
Route::post('devices/{device}/toggle-status', [DeviceController::class, 'toggleStatus'])->middleware('permission:devices.manage')->name('devices.toggle-status');
Route::post('devices/{device}/revoke-token', [DeviceController::class, 'revokeToken'])->middleware('permission:devices.manage')->name('devices.revoke-token');
Route::delete('devices/{device}', [DeviceController::class, 'destroy'])->middleware('permission:devices.manage')->name('devices.destroy');

// Advertisers
Route::get('advertisers', [AdvertiserController::class, 'index'])->middleware('permission:advertisers.view')->name('advertisers.index');
Route::post('advertisers', [AdvertiserController::class, 'store'])->middleware('permission:advertisers.manage')->name('advertisers.store');
Route::get('advertisers/{advertiser}', [AdvertiserController::class, 'show'])->middleware('permission:advertisers.view')->name('advertisers.show');
Route::put('advertisers/{advertiser}', [AdvertiserController::class, 'update'])->middleware('permission:advertisers.manage')->name('advertisers.update');
Route::delete('advertisers/{advertiser}', [AdvertiserController::class, 'destroy'])->middleware('permission:advertisers.manage')->name('advertisers.destroy');

// Campaigns
Route::post('campaigns/preview-targets', [CampaignController::class, 'previewTargets'])->middleware('permission:campaigns.view')->name('campaigns.preview-targets');
Route::get('campaigns', [CampaignController::class, 'index'])->middleware('permission:campaigns.view')->name('campaigns.index');
Route::get('campaigns/create', [CampaignController::class, 'create'])->middleware('permission:campaigns.create')->name('campaigns.create');
Route::post('campaigns', [CampaignController::class, 'store'])->middleware('permission:campaigns.create')->name('campaigns.store');
Route::get('campaigns/{campaign}', [CampaignController::class, 'show'])->middleware('permission:campaigns.view')->name('campaigns.show');
Route::get('campaigns/{campaign}/edit', [CampaignController::class, 'edit'])->middleware('permission:campaigns.edit')->name('campaigns.edit');
Route::put('campaigns/{campaign}', [CampaignController::class, 'update'])->middleware('permission:campaigns.edit')->name('campaigns.update');
Route::post('campaigns/{campaign}/publish', [CampaignController::class, 'publish'])->middleware('permission:campaigns.publish')->name('campaigns.publish');
Route::post('campaigns/{campaign}/pause', [CampaignController::class, 'pause'])->middleware('permission:campaigns.publish')->name('campaigns.pause');
Route::post('campaigns/{campaign}/resume', [CampaignController::class, 'resume'])->middleware('permission:campaigns.publish')->name('campaigns.resume');
Route::post('campaigns/{campaign}/archive', [CampaignController::class, 'archive'])->middleware('permission:campaigns.publish')->name('campaigns.archive');
Route::delete('campaigns/{campaign}', [CampaignController::class, 'destroy'])->middleware('permission:campaigns.delete')->name('campaigns.destroy');

// Creatives / media library
Route::post('live-streams/detect', [LiveStreamController::class, 'detect'])->middleware(['permission:creatives.manage', 'throttle:60,1']);
Route::post('live-streams', [LiveStreamController::class, 'store'])->middleware('permission:creatives.manage');
Route::get('creatives', [MediaController::class, 'index'])->middleware('permission:creatives.view')->name('creatives.index');
Route::post('creatives', [MediaController::class, 'store'])->middleware('permission:creatives.manage')->name('creatives.store');
Route::delete('creatives/{media}', [MediaController::class, 'destroy'])->middleware('permission:creatives.manage')->name('creatives.destroy');

// Quick play / instant content
Route::get('quick-play', [QuickPlayController::class, 'index'])->middleware('permission:quick_play.view')->name('quick-play.index');
Route::get('quick-play/create', [QuickPlayController::class, 'create'])->middleware('permission:quick_play.send')->name('quick-play.create');
Route::post('quick-play', [QuickPlayController::class, 'store'])->middleware('permission:quick_play.send')->name('quick-play.store');
Route::get('quick-play/{quickPlay}', [QuickPlayController::class, 'show'])->middleware('permission:quick_play.view')->name('quick-play.show');
Route::post('quick-play/{quickPlay}/retry', [QuickPlayController::class, 'retry'])->middleware('permission:quick_play.send')->name('quick-play.retry');
Route::delete('quick-play/{quickPlay}', [QuickPlayController::class, 'destroy'])->middleware('permission:quick_play.send')->name('quick-play.destroy');

// Analytics
Route::get('analytics', [AnalyticsController::class, 'index'])->middleware('permission:analytics.view')->name('analytics.index');
Route::get('analytics/export', [AnalyticsController::class, 'export'])->middleware('permission:analytics.view')->name('analytics.export');

// Users
Route::get('users', [UserController::class, 'index'])->middleware('permission:users.manage')->name('users.index');
Route::post('users', [UserController::class, 'store'])->middleware('permission:users.manage')->name('users.store');
Route::put('users/{user}', [UserController::class, 'update'])->middleware('permission:users.manage')->name('users.update');
Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('permission:users.manage')->name('users.destroy');

// Alerts
Route::get('alerts', [AlertController::class, 'index'])->middleware('permission:alerts.manage,devices.view')->name('alerts.index');
Route::post('alerts/{alert}/acknowledge', [AlertController::class, 'acknowledge'])->middleware('permission:alerts.manage')->name('alerts.acknowledge');
Route::post('alerts/{alert}/resolve', [AlertController::class, 'resolve'])->middleware('permission:alerts.manage')->name('alerts.resolve');

// Audit log
Route::get('audit', [AuditController::class, 'index'])->middleware('permission:audit.view')->name('audit.index');

// System settings
Route::get('android-updates', [AndroidUpdateController::class, 'index'])->middleware('permission:system.settings')->name('android-updates.index');
Route::post('android-updates', [AndroidUpdateController::class, 'store'])->middleware('permission:system.settings')->name('android-updates.store');
Route::get('settings', [SettingsController::class, 'index'])->middleware('permission:system.settings,roles.manage')->name('settings.index');
Route::put('settings', [SettingsController::class, 'update'])->middleware('permission:system.settings')->name('settings.update');
Route::put('settings/roles/{role}/permissions', [SettingsController::class, 'updateRolePermissions'])->middleware('permission:roles.manage')->name('settings.roles.permissions');
