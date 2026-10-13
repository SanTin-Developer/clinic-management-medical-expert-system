<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->string('source', 20)->default('web');
            $table->bigInteger('telegram_chat_id')->nullable();
            $table->bigInteger('telegram_message_id')->nullable();

            $table->unique(
                ['telegram_chat_id', 'telegram_message_id'],
                'conversation_messages_telegram_unique'
            );
        });

        Schema::table('telegram_delivery_logs', function (Blueprint $table) {
            $table->bigInteger('telegram_message_id')->nullable();
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->foreignId('conversation_message_id')->nullable()->constrained('conversation_messages')->nullOnDelete();

            $table->index('telegram_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_delivery_logs', function (Blueprint $table) {
            $table->dropForeign(['conversation_id']);
            $table->dropForeign(['conversation_message_id']);
            $table->dropIndex(['telegram_message_id']);
            $table->dropColumn(['telegram_message_id', 'conversation_id', 'conversation_message_id']);
        });

        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->dropUnique('conversation_messages_telegram_unique');
            $table->dropColumn(['source', 'telegram_chat_id', 'telegram_message_id']);
        });
    }
};
