<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_creatives', fn (Blueprint $table) => $table->json('configuration')->nullable());
        Schema::table('playback_events', fn (Blueprint $table) => $table->json('metadata')->nullable());
    }

    public function down(): void
    {
        Schema::table('campaign_creatives', fn (Blueprint $table) => $table->dropColumn('configuration'));
        Schema::table('playback_events', fn (Blueprint $table) => $table->dropColumn('metadata'));
    }
};
