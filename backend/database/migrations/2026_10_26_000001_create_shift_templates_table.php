<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configurable shift templates. The clinic's default shifts (Morning, Evening,
 * Night Watch) are seeded from here. Times are authored in the clinic timezone
 * (see config/attendance.php) and later projected to UTC for calculations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_templates', function (Blueprint $table) {
            $table->id();

            $table->string('name', 80)->unique();
            $table->string('type', 20)->default('day'); // day, evening, night, on_call

            $table->time('start_time');
            $table->time('clock_in_cutoff');   // scans are rejected at/after this local time
            $table->time('end_time');
            $table->boolean('crosses_midnight')->default(false);

            // Clock-out window (local time). Defaults to the scheduled end.
            $table->time('clock_out_opens_at')->nullable();
            $table->time('clock_out_closes_at')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_templates');
    }
};
