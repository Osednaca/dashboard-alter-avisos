<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('activation_code')->nullable()->unique();
            $table->string('device_token_hash', 64)->nullable()->unique();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('token_revoked_at')->nullable();
            $table->string('status')->default('pending_activation')->index();
            $table->string('app_version')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->string('last_ip', 45)->nullable();
            $table->unsignedBigInteger('storage_total')->nullable();
            $table->unsignedBigInteger('storage_free')->nullable();
            $table->string('current_manifest_version')->nullable();
            $table->string('pending_manifest_version')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->foreignId('current_layout_id')->nullable()->constrained('layouts')->nullOnDelete();
            $table->foreignId('current_playlist_id')->nullable()->constrained('playlists')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
            $table->index(['status', 'last_seen_at']);
        });

        Schema::create('device_activations', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->uuid('device_uuid')->nullable()->index();
            $table->string('status')->default('pending')->index();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('device_name')->nullable();
            $table->string('app_version')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();
        });

        // High frequency rows. A retention job prunes rows older than the
        // configured window, and daily aggregates keep long term history.
        Schema::create('device_heartbeats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->timestamp('recorded_at');
            $table->string('app_version')->nullable();
            $table->unsignedBigInteger('available_storage')->nullable();
            $table->string('manifest_version')->nullable();
            $table->string('player_status')->nullable();
            $table->string('network_status')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['device_id', 'recorded_at']);
            $table->index('recorded_at');
        });

        Schema::create('device_manifests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('version');
            $table->string('checksum', 64)->nullable();
            $table->json('payload');
            $table->string('status')->default('pending')->index();
            $table->timestamp('generated_at');
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'version']);
        });

        Schema::create('device_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('command')->index();
            $table->json('payload')->nullable();
            $table->string('status')->default('pending')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['device_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_commands');
        Schema::dropIfExists('device_manifests');
        Schema::dropIfExists('device_heartbeats');
        Schema::dropIfExists('device_activations');
        Schema::dropIfExists('devices');
    }
};
