<?php

namespace App\Providers;

use App\Domain\Advertisers\Models\Advertiser;
use App\Domain\Businesses\Models\Business;
use App\Domain\Campaigns\Models\Campaign;
use App\Domain\Devices\Models\Device;
use App\Domain\Locations\Models\Location;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Operations\Models\Alert;
use App\Domain\Users\Enums\RoleEnum;
use App\Models\User;
use App\Policies\AdvertiserPolicy;
use App\Policies\AlertPolicy;
use App\Policies\BusinessPolicy;
use App\Policies\CampaignPolicy;
use App\Policies\DevicePolicy;
use App\Policies\LocationPolicy;
use App\Policies\MediaAssetPolicy;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerAuthorization();
        $this->registerPolicies();
        $this->registerRateLimiters();
        $this->registerFactories();
    }

    /**
     * Domain models live under App\Domain\*, so map factories by class basename.
     */
    protected function registerFactories(): void
    {
        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function registerPolicies(): void
    {
        Gate::policy(Business::class, BusinessPolicy::class);
        Gate::policy(Location::class, LocationPolicy::class);
        Gate::policy(Device::class, DevicePolicy::class);
        Gate::policy(Campaign::class, CampaignPolicy::class);
        Gate::policy(Advertiser::class, AdvertiserPolicy::class);
        Gate::policy(MediaAsset::class, MediaAssetPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Alert::class, AlertPolicy::class);
    }

    /**
     * Super admins bypass all gates. Every other ability falls through to the
     * policies, which are backed by granular permissions.
     */
    protected function registerAuthorization(): void
    {
        Gate::before(function (User $user, string $ability) {
            if ($user->hasRole(RoleEnum::SuperAdmin)) {
                return true;
            }

            if (str_contains($ability, '.')) {
                return $user->hasPermission($ability) ? true : null;
            }

            return null;
        });
    }

    protected function registerRateLimiters(): void
    {
        RateLimiter::for('device-activation', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('device-recovery', function (Request $request) {
            $key = $request->input('recovery_key');
            $fingerprint = hash('sha256', is_string($key) ? substr($key, 0, 256) : 'invalid');

            return [
                Limit::perMinute(10)->by('recovery-ip:'.$request->ip()),
                Limit::perMinute(5)->by('recovery-key:'.$fingerprint),
            ];
        });

        RateLimiter::for('device-api', function (Request $request) {
            $device = $request->user();

            return Limit::perMinute(120)->by($device?->getKey() ?? $request->ip());
        });

        RateLimiter::for('device-playback', function (Request $request) {
            $device = $request->user();

            return Limit::perMinute(30)->by($device?->getKey() ?? $request->ip());
        });
    }
}
