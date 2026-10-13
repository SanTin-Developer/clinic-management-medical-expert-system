<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two-stage email change requests. The new address is only applied after
     * the owner confirms a code delivered to that new address.
     */
    public function up(): void
    {
        Schema::create('email_change_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('new_email');
            $table->string('code_hash');
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('max_attempts')->default(5);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_change_requests');
    }
};
