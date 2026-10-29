<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bilingual public website content.
 *
 * Every translatable column gets a nullable `_km` sibling. The public API
 * returns both variants and the website falls back to the English column
 * whenever the Khmer one is empty, so existing content keeps working
 * unchanged and editors can translate incrementally.
 */
return new class extends Migration
{
    /**
     * @var array<string, array<string, 'string'|'text'|'longText'>>
     */
    private array $columns = [
        'public_pages' => [
            'title_km' => 'string',
            'excerpt_km' => 'text',
            'body_km' => 'longText',
            'seo_title_km' => 'string',
            'seo_description_km' => 'text',
        ],
        'services' => [
            'title_km' => 'string',
            'short_description_km' => 'text',
            'description_km' => 'longText',
        ],
        'public_team_profiles' => [
            'position_km' => 'string',
            'specialization_km' => 'string',
            'bio_km' => 'text',
        ],
        'gallery_items' => [
            'title_km' => 'string',
            'caption_km' => 'text',
            'category_km' => 'string',
        ],
        'articles' => [
            'title_km' => 'string',
            'excerpt_km' => 'text',
            'body_km' => 'longText',
        ],
        'content_blocks' => [
            'title_km' => 'string',
            'body_km' => 'longText',
        ],
        'website_settings' => [
            'tagline_km' => 'string',
            'address_km' => 'string',
            'business_hours_km' => 'text',
            'seo_title_km' => 'string',
            'seo_description_km' => 'text',
        ],
    ];

    public function up(): void
    {
        foreach ($this->columns as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns): void {
                foreach ($columns as $column => $type) {
                    if (Schema::hasColumn($table, $column)) {
                        continue;
                    }

                    match ($type) {
                        'longText' => $blueprint->longText($column)->nullable(),
                        'text' => $blueprint->text($column)->nullable(),
                        default => $blueprint->string($column, 255)->nullable(),
                    };
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->columns as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns): void {
                foreach (array_keys($columns) as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        $blueprint->dropColumn($column);
                    }
                }
            });
        }
    }
};
