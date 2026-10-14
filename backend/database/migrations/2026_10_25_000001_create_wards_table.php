<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wards', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('code', 30)->unique();
            $table->string('floor')->nullable();
            $table->string('ward_type', 30)->default('general');
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index('ward_type');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wards');
    }
};
