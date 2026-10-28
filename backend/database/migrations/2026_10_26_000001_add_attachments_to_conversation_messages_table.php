<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->string('attachment_kind', 16)->nullable()->after('body');
            $table->string('attachment_path')->nullable()->after('attachment_kind');
            $table->string('attachment_name')->nullable()->after('attachment_path');
            $table->string('attachment_mime', 100)->nullable()->after('attachment_name');
            $table->unsignedBigInteger('attachment_size')->nullable()->after('attachment_mime');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->dropColumn([
                'attachment_kind',
                'attachment_path',
                'attachment_name',
                'attachment_mime',
                'attachment_size',
            ]);
        });
    }
};
