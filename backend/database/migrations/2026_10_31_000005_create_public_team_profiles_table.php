<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('public_team_profiles', function (Blueprint $table) {
            $table->id();

            $table->string('name', 255);
            $table->string('position', 255);
            $table->string('specialization', 255)->nullable();
            $table->text('bio')->nullable();
            $table->foreignId('photo_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();

            $table->boolean('is_featured')->default(false);
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('sort_order')->default(0);

            $table->foreignId('staff_id')->nullable()->unique()->constrained('staff')->nullOnDelete();

            $table->timestamps();

            $table->index('is_published');
            $table->index('is_featured');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('public_team_profiles');
    }
};
