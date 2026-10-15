<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clinic holidays used to exempt staff from absence finalization.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_holidays', function (Blueprint $table) {
            $table->id();

            $table->date('holiday_date')->unique();
            $table->string('name', 120);
            $table->boolean('is_recurring')->default(false); // repeats annually by month/day
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('is_recurring');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_holidays');
    }
};
