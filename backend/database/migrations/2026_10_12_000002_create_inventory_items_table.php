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
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('medicine_id')
                ->constrained('medicines')
                ->cascadeOnDelete();

            $table->string('batch_number', 100)->nullable();
            $table->integer('quantity')->default(0);
            $table->date('expiration_date')->nullable();
            $table->date('received_date')->nullable();
            $table->string('location', 100)->nullable();

            $table->timestamps();

            $table->index('medicine_id');
            $table->index('expiration_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
