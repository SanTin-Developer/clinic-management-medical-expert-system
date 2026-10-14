<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allow an invitation to target an existing staff profile. When the invitee
 * accepts, the resulting User account is linked to that staff profile so a
 * single employee never ends up with duplicate directory records.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_invitations', function (Blueprint $table) {
            $table->foreignId('staff_id')
                ->nullable()
                ->after('user_id')
                ->constrained('staff')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('staff_invitations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('staff_id');
        });
    }
};
