<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('owner');
            $table->string('type')->index();
            $table->string('filename');
            $table->string('original_name')->nullable();
            $table->string('storage_path');
            $table->string('thumbnail_path')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->unsignedBigInteger('filesize')->nullable();
            $table->string('checksum', 64)->nullable()->index();
            $table->string('processing_status')->default('pending')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['type', 'processing_status']);
        });

        Schema::create('layouts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('orientation')->default('landscape')->index();
            $table->unsignedTinyInteger('business_percentage')->default(70);
            $table->unsignedTinyInteger('advertising_percentage')->default(30);
            $table->json('configuration')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('playlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('advertiser_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('type')->index();
            $table->string('status')->default('draft')->index();
            $table->timestamps();
        });

        Schema::create('playlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('playlist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_asset_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('duration')->default(10);
            $table->string('transition')->default('fade');
            $table->json('configuration')->nullable();
            $table->timestamps();

            $table->index(['playlist_id', 'sort_order']);
        });

        Schema::create('content_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('playlist_id')->constrained()->cascadeOnDelete();
            $table->time('daily_start_time')->nullable();
            $table->time('daily_end_time')->nullable();
            $table->json('days_of_week')->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_schedules');
        Schema::dropIfExists('playlist_items');
        Schema::dropIfExists('playlists');
        Schema::dropIfExists('layouts');
        Schema::dropIfExists('media_assets');
    }
};
