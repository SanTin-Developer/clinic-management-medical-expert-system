<?php

use App\Models\ContentBlock;
use App\Models\WebsiteSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Move the homepage copy out of the global website settings row and into the
     * per-section content blocks that own it now.
     *
     * The hero title, hero subtitle, hero photo and the clinic blurb used to
     * live in `website_settings`, which made a single field drive several
     * unrelated sections of the homepage. They are edited per section from the
     * admin CMS (Page Content → Home Page / Footer) now, so the existing
     * values are copied into the matching blocks.
     *
     * Nothing is deleted: the legacy columns and their rows stay in place, the
     * content blocks keep their rows and the referenced Cloudinary asset stays
     * linked. The settings row only releases its pointer to the hero photo so
     * the content block is the single owner of that image.
     * Blocks that already carry content are left untouched so admin edits are
     * never overwritten.
     */
    public function up(): void
    {
        $settings = WebsiteSetting::query()->first();

        if ($settings === null) {
            return;
        }

        $this->copyText($settings->hero_title, 'home', 'home_hero_title');
        $this->copyText($settings->hero_subtitle, 'home', 'home_hero_subtitle');
        $this->copyText($settings->about_summary, 'home', 'home_hero_highlight');
        $this->copyText($settings->about_summary, 'home', 'home_about_text');
        $this->copyText($settings->about_summary, 'footer', 'footer_about_text');

        if ($settings->hero_media_id !== null) {
            $this->copyImage($settings->hero_media_id, 'home', 'home_hero_image');

            $settings->hero_media_id = null;
            $settings->save();
        }
    }

    /**
     * The copy now lives in the content blocks. Reverting would delete admin
     * edits, so the data migration is intentionally irreversible.
     */
    public function down(): void
    {
        //
    }

    /**
     * Copy a settings value into a content block, keeping any copy the admin
     * has already written there.
     */
    private function copyText(?string $value, string $group, string $key): void
    {
        $value = trim((string) $value);

        if ($value === '') {
            return;
        }

        $block = ContentBlock::query()->firstOrNew(['group' => $group, 'key' => $key]);

        if ($block->exists && trim((string) $block->body) !== '') {
            return;
        }

        $block->body = $value;
        $block->save();
    }

    /**
     * Re-point a settings image at a content block without touching the
     * media asset itself.
     */
    private function copyImage(int $mediaId, string $group, string $key): void
    {
        $block = ContentBlock::query()->firstOrNew(['group' => $group, 'key' => $key]);

        if ($block->exists && $block->image_media_id !== null) {
            return;
        }

        $block->title = 'Homepage hero image';
        $block->image_media_id = $mediaId;
        $block->save();
    }
};
