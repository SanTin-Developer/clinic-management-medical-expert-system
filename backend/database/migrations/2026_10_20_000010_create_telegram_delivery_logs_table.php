<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_delivery_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->bigInteger('telegram_chat_id')->nullable();
            $table->string('event_type', 50);
            $table->string('subject', 200)->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('error', 255)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamps();

            $table->index('status');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_delivery_logs');
    }
};
