<?php

use App\Domain\Campaigns\Actions\ResolveCampaignTargets;
use App\Domain\Campaigns\Models\Campaign;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('state', 120)->default('');
            $table->string('country', 120);
            $table->string('timezone')->default('America/Bogota');
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['name', 'state', 'country']);
        });

        Schema::create('city_business', function (Blueprint $table) {
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['city_id', 'business_id']);
            $table->index('business_id');
        });

        // Preserve branch/device identifiers. Only populate the new city catalogue.
        DB::table('locations')->orderBy('id')->chunkById(200, function ($locations) {
            foreach ($locations as $location) {
                $identity = ['name' => $location->city, 'state' => $location->state ?? '', 'country' => $location->country];
                $cityId = DB::table('cities')->where($identity)->value('id');
                if (! $cityId) {
                    $cityId = DB::table('cities')->insertGetId([
                        ...$identity, 'timezone' => $location->timezone, 'status' => 'active',
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                DB::table('city_business')->insertOrIgnore([
                    'city_id' => $cityId, 'business_id' => $location->business_id,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
        DB::table('devices')->update(['manifest_dirty' => true]);
        Campaign::query()->whereHas('targets', fn ($q) => $q->where('target_type', 'city'))
            ->chunkById(100, function ($campaigns) {
                foreach ($campaigns as $campaign) {
                    DB::table('campaigns')->where('id', $campaign->id)->update([
                        'target_screen_count' => app(ResolveCampaignTargets::class)->devicesFor($campaign)->count(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('city_business');
        Schema::dropIfExists('cities');
    }
};
