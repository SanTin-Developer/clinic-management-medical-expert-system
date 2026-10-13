<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Server-side step-up authorizations. Sensitive operations require the
     * caller to prove their identity again, producing a short-lived grant that
     * is bound to the requesting user and the specific action.
     */
    public function up(): void
    {
        Schema::create('step_up_authorizations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('token_hash')->unique();
            $table->string('action', 100);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'action', 'consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('step_up_authorizations');
    }
};
