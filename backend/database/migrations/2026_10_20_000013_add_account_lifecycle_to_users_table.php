<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the staff account lifecycle fields. Existing accounts default to the
     * "active" status so that pre-existing logins keep working untouched.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('staff_id', 50)->nullable()->unique()->after('username');
            $table->string('phone', 30)->nullable()->after('staff_id');
            $table->string('account_status', 20)->default('active')->index()->after('phone');
            $table->timestamp('activated_at')->nullable()->after('email_verified_at');
            $table->timestamp('suspended_at')->nullable()->after('activated_at');
            $table->string('suspended_reason')->nullable()->after('suspended_at');
            $table->timestamp('last_login_at')->nullable()->after('suspended_reason');
            $table->timestamp('password_changed_at')->nullable()->after('last_login_at');
            $table->boolean('must_change_password')->default(false)->after('password_changed_at');
            $table->unsignedInteger('failed_login_attempts')->default(0)->after('must_change_password');
            $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');

            $table->foreignId('created_by')
                ->nullable()
                ->after('locked_until')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropUnique(['staff_id']);
            $table->dropColumn([
                'staff_id',
                'phone',
                'account_status',
                'activated_at',
                'suspended_at',
                'suspended_reason',
                'last_login_at',
                'password_changed_at',
                'must_change_password',
                'failed_login_attempts',
                'locked_until',
            ]);
        });
    }
};
