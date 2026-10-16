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
        Schema::create('articles', function (Blueprint $table) {
            $table->id();

            $table->string('title', 255);
            $table->string('slug', 255)->unique();
            $table->text('excerpt')->nullable();
            $table->longText('body');
            $table->foreignId('cover_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('category', 100)->nullable();
            $table->json('tags')->nullable();

            $table->string('status', 20)->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('views')->default(0);

            $table->timestamp('published_at')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('status');
            $table->index('is_featured');
            $table->index('published_at');
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
