<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('slug');
        });

        Schema::table('content_schedules', function (Blueprint $table) {
            $table->string('name')->nullable()->after('business_id');
            $table->foreignId('location_id')->nullable()->after('business_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('content_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_id');
            $table->dropColumn('name');
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('logo_path');
        });
    }
};
