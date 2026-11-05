<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->date('stat_date');
            $table->unsignedBigInteger('playbacks_count')->default(0);
            $table->unsignedBigInteger('completed_count')->default(0);
            $table->unsignedBigInteger('failures')->default(0);
            $table->unsignedBigInteger('unique_devices')->default(0);
            $table->unsignedBigInteger('unique_businesses')->default(0);
            $table->unsignedBigInteger('total_duration')->default(0);
            $table->decimal('completion_rate', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(['campaign_id', 'stat_date']);
            $table->index('stat_date');
        });

        Schema::create('device_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->date('stat_date');
            $table->unsignedBigInteger('playbacks_count')->default(0);
            $table->unsignedBigInteger('completed_count')->default(0);
            $table->unsignedBigInteger('failures')->default(0);
            $table->unsignedBigInteger('total_duration')->default(0);
            $table->unsignedInteger('uptime_seconds')->default(0);
            $table->timestamps();

            $table->unique(['device_id', 'stat_date']);
            $table->index('stat_date');
        });

        Schema::create('business_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->date('stat_date');
            $table->unsignedBigInteger('playbacks_count')->default(0);
            $table->unsignedBigInteger('completed_count')->default(0);
            $table->unsignedBigInteger('failures')->default(0);
            $table->unsignedBigInteger('unique_devices')->default(0);
            $table->unsignedBigInteger('total_duration')->default(0);
            $table->timestamps();

            $table->unique(['business_id', 'stat_date']);
            $table->index('stat_date');
        });

        Schema::create('city_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->string('city')->index();
            $table->date('stat_date');
            $table->unsignedBigInteger('playbacks_count')->default(0);
            $table->unsignedBigInteger('unique_devices')->default(0);
            $table->unsignedBigInteger('unique_businesses')->default(0);
            $table->unsignedBigInteger('total_duration')->default(0);
            $table->timestamps();

            $table->unique(['city', 'stat_date']);
            $table->index('stat_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('city_daily_stats');
        Schema::dropIfExists('business_daily_stats');
        Schema::dropIfExists('device_daily_stats');
        Schema::dropIfExists('campaign_daily_stats');
    }
};
