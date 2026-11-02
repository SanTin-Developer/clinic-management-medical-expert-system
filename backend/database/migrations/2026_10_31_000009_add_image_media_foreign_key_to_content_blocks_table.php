<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The `content_blocks` table was created before `media_assets` existed, so
     * its `image_media_id` foreign key is added here, once both tables exist.
     * The guard keeps this migration safe for databases that already have the
     * constraint from before the migration was split.
     */
    public function up(): void
    {
        if (! Schema::hasTable('content_blocks') || ! Schema::hasTable('media_assets')) {
            return;
        }

        if ($this->hasImageMediaForeignKey()) {
            return;
        }

        Schema::table('content_blocks', function (Blueprint $table) {
            $table->foreign('image_media_id')
                ->references('id')
                ->on('media_assets')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('content_blocks') || ! $this->hasImageMediaForeignKey()) {
            return;
        }

        Schema::table('content_blocks', function (Blueprint $table) {
            $table->dropForeign(['image_media_id']);
        });
    }

    private function hasImageMediaForeignKey(): bool
    {
        foreach (Schema::getForeignKeys('content_blocks') as $foreignKey) {
            if (($foreignKey['columns'] ?? []) === ['image_media_id']) {
                return true;
            }
        }

        return false;
    }
};
