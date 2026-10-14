<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The clinic staff directory. Every employee has a row here regardless of
 * whether they hold a system login. A staff profile may exist without a linked
 * User account (user_id is nullable); when the employee is later granted
 * access, the resulting account is linked back to this profile.
 *
 * Employment status (employed / on_leave / resigned / terminated / probation)
 * is intentionally independent of the User account_status lifecycle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();

            $table->string('staff_number', 50)->unique();

            $table->string('first_name');
            $table->string('last_name');
            $table->string('gender', 20)->nullable();
            $table->date('date_of_birth')->nullable();

            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('photo_path')->nullable();

            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 30)->nullable();

            // Employment
            $table->string('job_title')->nullable();
            $table->string('department')->nullable();
            $table->string('employment_type', 30)->nullable(); // full_time, part_time, contract, intern
            $table->string('employment_status', 30)->default('employed')->index(); // employed, on_leave, resigned, terminated, probation
            $table->date('hire_date')->nullable();
            $table->string('work_schedule')->nullable();

            $table->foreignId('supervisor_id')
                ->nullable()
                ->after('work_schedule');

            // Credentials & documents metadata
            $table->string('education')->nullable();
            $table->text('qualifications')->nullable();
            $table->string('license_number')->nullable();
            $table->date('license_expiry')->nullable();

            // Compensation (protected by staff.salary.* permissions)
            $table->decimal('salary_amount', 12, 2)->nullable();
            $table->string('pay_frequency', 20)->nullable(); // monthly, biweekly, hourly
            $table->decimal('allowances', 12, 2)->nullable();

            // Optional link to a system user account (nullable: profile can exist without a login)
            $table->foreignId('user_id')
                ->nullable()
                ->after('id')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['department']);
            $table->index(['job_title']);
        });

        // The self-referencing supervisor FK is added after the table exists so
        // it works on PostgreSQL (a table cannot reference itself in its own
        // CREATE TABLE statement).
        Schema::table('staff', function (Blueprint $table) {
            $table->foreign('supervisor_id')->references('id')->on('staff')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
