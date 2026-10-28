<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_message_user_deletes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_message_id')->constrained('conversation_messages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['conversation_message_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_message_user_deletes');
    }
};
