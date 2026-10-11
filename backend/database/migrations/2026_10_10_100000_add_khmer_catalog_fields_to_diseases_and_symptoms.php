<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add optional bilingual catalog fields for the Knowledge Base Starter
     * (100-disease catalog). All columns are nullable and additive; the rule
     * engine continues to use `name`, `code`, and `is_active` only.
     */
    public function up(): void
    {
        if (Schema::hasTable('diseases') && ! Schema::hasColumn('diseases', 'name_km')) {
            Schema::table('diseases', function (Blueprint $table) {
                $table->string('name_km')->nullable()->after('name');
                $table->string('category')->nullable()->after('name_km');
            });
        }

        if (Schema::hasTable('symptoms') && ! Schema::hasColumn('symptoms', 'name_km')) {
            Schema::table('symptoms', function (Blueprint $table) {
                $table->string('name_km')->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('diseases')) {
            Schema::table('diseases', function (Blueprint $table) {
                if (Schema::hasColumn('diseases', 'category')) {
                    $table->dropColumn('category');
                }
                if (Schema::hasColumn('diseases', 'name_km')) {
                    $table->dropColumn('name_km');
                }
            });
        }

        if (Schema::hasTable('symptoms')) {
            Schema::table('symptoms', function (Blueprint $table) {
                if (Schema::hasColumn('symptoms', 'name_km')) {
                    $table->dropColumn('name_km');
                }
            });
        }
    }
};
