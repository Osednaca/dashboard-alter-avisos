<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ad-hoc, single-shot playback that never requires a campaign. It is
        // delivered through the existing device command pipeline and the device
        // restores its previously scheduled content when the play finishes.
        Schema::create('quick_plays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('media_asset_id')->constrained()->cascadeOnDelete();
            $table->string('display_mode')->index();
            $table->string('scope')->index();
            $table->unsignedInteger('duration')->nullable();
            $table->string('status')->default('sending')->index();
            $table->unsignedInteger('targets_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('quick_play_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quick_play_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('command_id')->nullable()->constrained('device_commands')->nullOnDelete();
            $table->string('status')->default('pending')->index();
            $table->string('display_mode');
            $table->unsignedInteger('duration')->nullable();
            $table->unsignedBigInteger('previous_layout_id')->nullable();
            $table->unsignedBigInteger('previous_playlist_id')->nullable();
            $table->string('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['quick_play_id', 'device_id']);
            $table->index(['device_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_play_devices');
        Schema::dropIfExists('quick_plays');
    }
};
