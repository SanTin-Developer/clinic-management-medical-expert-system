<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Short-lived challenges issued after a valid password when a second
     * factor (TOTP or recovery code) is still required before a token is given.
     */
    public function up(): void
    {
        Schema::create('two_factor_challenges', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('challenge_token')->unique();
            $table->string('method', 20)->default('totp');
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('max_attempts')->default(5);
            $table->boolean('remember_device')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('two_factor_challenges');
    }
};
