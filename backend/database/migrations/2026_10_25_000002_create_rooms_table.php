<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ward_id')
                ->constrained('wards')
                ->cascadeOnDelete();

            $table->string('room_number', 30);
            $table->string('room_type', 30)->default('general');
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['ward_id', 'room_number']);
            $table->index('ward_id');
            $table->index('room_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
