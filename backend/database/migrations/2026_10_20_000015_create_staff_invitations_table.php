<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Administrator-issued staff invitations. Only a hash of the invitation
     * token is persisted; the plaintext token is emailed exactly once.
     */
    public function up(): void
    {
        Schema::create('staff_invitations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('invited_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('email')->index();
            $table->string('name');
            $table->string('role', 50);
            $table->string('token_hash')->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['email', 'accepted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_invitations');
    }
};
