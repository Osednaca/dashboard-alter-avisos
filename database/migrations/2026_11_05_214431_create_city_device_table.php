<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('city_device', function (Blueprint $table) {
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['city_id', 'device_id']);
            $table->index('device_id');
        });

        Schema::table('device_activations', function (Blueprint $table) {
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
        });

        // Snapshot the previous city-to-business expansion so existing campaigns
        // keep exactly the same device targets without retaining that coupling.
        DB::table('city_business')->join('devices', 'devices.business_id', '=', 'city_business.business_id')
            ->select('city_business.city_id', 'devices.id as device_id')
            ->orderBy('city_business.city_id')->orderBy('devices.id')
            ->chunk(500, function ($rows) {
                $now = now();
                DB::table('city_device')->insertOrIgnore($rows->map(fn ($row) => [
                    'city_id' => $row->city_id, 'device_id' => $row->device_id,
                    'created_at' => $now, 'updated_at' => $now,
                ])->all());
            });
    }

    public function down(): void
    {
        Schema::table('device_activations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('city_id');
        });
        Schema::dropIfExists('city_device');
    }
};
