<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Short-lived rotating QR tokens. Only the SHA-256 hash of the token is stored;
 * the raw value is rendered into the QR code and never persisted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_session_tokens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('attendance_session_id')->constrained('attendance_sessions')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();

            $table->index(['attendance_session_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_session_tokens');
    }
};
