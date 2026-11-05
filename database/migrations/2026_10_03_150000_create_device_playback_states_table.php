<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_playback_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('session_id');
            $table->unsignedBigInteger('sequence');
            $table->json('payload');
            $table->timestamp('received_at', 3);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_playback_states');
    }
};
