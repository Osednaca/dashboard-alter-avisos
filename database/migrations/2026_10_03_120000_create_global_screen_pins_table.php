<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_screen_pins', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('pin_hash');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_screen_pins');
    }
};
