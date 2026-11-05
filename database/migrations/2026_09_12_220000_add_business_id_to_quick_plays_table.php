<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quick_plays', function (Blueprint $table) {
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quick_plays', fn (Blueprint $table) => $table->dropConstrainedForeignId('business_id'));
    }
};
