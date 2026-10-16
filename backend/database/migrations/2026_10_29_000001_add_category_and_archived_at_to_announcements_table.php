<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->string('category', 40)->nullable()->after('body');
            $table->timestamp('archived_at')->nullable()->after('expires_at');

            $table->index('category');
            $table->index('archived_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropIndex(['archived_at']);
            $table->dropColumn(['category', 'archived_at']);
        });
    }
};
