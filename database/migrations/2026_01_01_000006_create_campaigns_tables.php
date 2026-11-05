<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advertiser_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('draft')->index();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->time('daily_start_time')->nullable();
            $table->time('daily_end_time')->nullable();
            $table->json('days_of_week')->nullable();
            $table->unsignedInteger('priority')->default(5);
            $table->unsignedBigInteger('impressions_goal')->nullable();
            $table->unsignedBigInteger('playback_goal')->nullable();
            $table->decimal('budget', 12, 2)->nullable();
            $table->unsignedInteger('target_screen_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'starts_at', 'ends_at']);
        });

        Schema::create('campaign_creatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_asset_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('duration')->default(10);
            $table->unsignedInteger('weight')->default(10);
            $table->unsignedInteger('position')->default(0);
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->unique(['campaign_id', 'media_asset_id']);
        });

        Schema::create('campaign_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('target_type')->index();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('target_value')->nullable();
            $table->boolean('is_exclusion')->default(false);
            $table->timestamps();

            $table->index(['campaign_id', 'target_type']);
            $table->unique(['campaign_id', 'target_type', 'target_id', 'target_value'], 'campaign_targets_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_targets');
        Schema::dropIfExists('campaign_creatives');
        Schema::dropIfExists('campaigns');
    }
};
