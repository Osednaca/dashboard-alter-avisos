<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Proof of play. Devices upload batches; analytics are derived from
        // this authoritative ledger, never from real-time per-second traffic.
        Schema::create('playback_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('creative_id')->nullable()->constrained('campaign_creatives')->nullOnDelete();
            $table->foreignId('playlist_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('media_asset_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_played')->default(0);
            $table->boolean('completed')->default(false);
            $table->string('error_code')->nullable();
            $table->unsignedBigInteger('manifest_version')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['device_id', 'started_at']);
            $table->index(['campaign_id', 'started_at']);
            $table->index(['media_asset_id', 'started_at']);
            $table->index('created_at');
            $table->index(['completed', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playback_events');
    }
};
