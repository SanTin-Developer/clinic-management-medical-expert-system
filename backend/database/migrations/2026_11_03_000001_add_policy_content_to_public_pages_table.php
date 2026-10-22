<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Adds optional structured bilingual content without changing existing pages. */
    public function up(): void
    {
        Schema::table('public_pages', function (Blueprint $table) {
            $table->json('policy_content')->nullable()->after('body');
            $table->text('internal_notes')->nullable()->after('policy_content');
        });
    }

    public function down(): void
    {
        Schema::table('public_pages', function (Blueprint $table) {
            $table->dropColumn(['policy_content', 'internal_notes']);
        });
    }
};
