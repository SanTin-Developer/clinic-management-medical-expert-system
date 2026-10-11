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
        Schema::create('medicines', function (Blueprint $table) {
            $table->id();

            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->string('generic_name', 255)->nullable();
            $table->string('category', 100)->nullable();
            $table->string('form', 50)->nullable();
            $table->string('strength', 100)->nullable();
            $table->string('unit', 50)->nullable();
            $table->string('manufacturer', 255)->nullable();
            $table->text('description')->nullable();

            $table->decimal('price', 12, 2)->default(0);
            $table->integer('reorder_level')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('name');
            $table->index('category');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medicines');
    }
};
