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
        Schema::create('public_pages', function (Blueprint $table) {
            $table->id();

            $table->string('title', 255);
            $table->string('slug', 255)->unique();
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->foreignId('cover_media_id')->nullable()->constrained('media_assets')->nullOnDelete();

            $table->string('status', 20)->default('draft');
            $table->boolean('is_system')->default(false);
            $table->boolean('show_in_footer')->default(false);

            $table->string('seo_title', 255)->nullable();
            $table->text('seo_description')->nullable();

            $table->timestamp('published_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('status');
            $table->index('show_in_footer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('public_pages');
    }
};
