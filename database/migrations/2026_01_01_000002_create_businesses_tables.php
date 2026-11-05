<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category')->index();
            $table->string('status')->default('onboarding')->index();
            $table->string('timezone')->default('America/Bogota');
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'category']);
        });

        Schema::create('business_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('staff');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['business_id', 'user_id']);
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('city')->index();
            $table->string('state')->nullable();
            $table->string('country')->default('Colombia');
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('timezone')->default('America/Bogota');
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->index(['city', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
        Schema::dropIfExists('business_users');
        Schema::dropIfExists('businesses');
    }
};
