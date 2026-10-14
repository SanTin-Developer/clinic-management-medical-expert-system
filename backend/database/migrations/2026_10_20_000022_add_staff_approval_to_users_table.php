<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the mandatory staff-admin approval trail. When an invited staff
     * member accepts their invitation they now land in the "pending_approval"
     * state; an authorized administrator must approve (or reject) the account
     * before it can authenticate. Every column is nullable so existing active
     * accounts are completely unaffected.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('approved_by')
                ->nullable()
                ->after('activated_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')->nullable()->after('approved_by');

            $table->foreignId('rejected_by')
                ->nullable()
                ->after('approved_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            $table->string('rejection_reason')->nullable()->after('rejected_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn([
                'approved_at',
                'rejected_at',
                'rejection_reason',
            ]);
        });
    }
};
