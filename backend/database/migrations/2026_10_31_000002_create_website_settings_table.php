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
        Schema::create('website_settings', function (Blueprint $table) {
            $table->id();

            // Branding
            $table->string('site_name', 255)->default('RoyalCare');
            $table->string('tagline', 255)->nullable();
            $table->foreignId('logo_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->foreignId('favicon_media_id')->nullable()->constrained('media_assets')->nullOnDelete();

            // Homepage hero
            $table->string('hero_title', 255)->nullable();
            $table->string('hero_subtitle', 500)->nullable();
            $table->foreignId('hero_media_id')->nullable()->constrained('media_assets')->nullOnDelete();

            // Clinic information
            $table->text('about_summary')->nullable();
            $table->string('primary_phone', 50)->nullable();
            $table->string('secondary_phone', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('address', 255)->nullable();
            $table->text('map_embed_url')->nullable();
            $table->text('business_hours')->nullable();

            // Social links
            $table->string('facebook_url', 255)->nullable();
            $table->string('twitter_url', 255)->nullable();
            $table->string('instagram_url', 255)->nullable();
            $table->string('linkedin_url', 255)->nullable();
            $table->string('youtube_url', 255)->nullable();

            // SEO defaults
            $table->string('seo_title', 255)->nullable();
            $table->text('seo_description')->nullable();
            $table->string('seo_keywords', 500)->nullable();

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_settings');
    }
};
