<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_connections', function (Blueprint $table) {
            // Integration state only: which conversation a linked Telegram chat
            // is currently "focused" on for free-text messages. A null value
            // means no explicit selection.
            $table->foreignId('active_conversation_id')
                ->nullable()
                ->constrained('conversations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('telegram_connections', function (Blueprint $table) {
            $table->dropForeign(['active_conversation_id']);
            $table->dropColumn('active_conversation_id');
        });
    }
};
