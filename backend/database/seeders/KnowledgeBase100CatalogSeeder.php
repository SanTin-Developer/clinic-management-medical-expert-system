<?php

namespace Database\Seeders;

use App\Models\Disease;
use App\Models\Symptom;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Imports the bilingual Knowledge Base Starter (100 diseases / 60 symptoms)
 * into the live tables as INACTIVE catalog records (is_active = false) pending
 * clinical review.
 *
 * - Safe/idempotent: keyed on the stable `code` (KB-###, SYM-###) via
 *   updateOrCreate(); existing active record codes (DIS-..., SYM-...) are
 *   never touched.
 * - Never fabricates disease-symptom mappings or expert rules.
 * - Data source: database/data/knowledge_base_100/knowledge_base_starter.json.
 */
class KnowledgeBase100CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $dataFile = database_path('data/knowledge_base_100/knowledge_base_starter.json');

        if (! File::exists($dataFile)) {
            Log::warning('Knowledge Base 100 catalog JSON not found; seeder skipped.', ['file' => $dataFile]);

            $this->command?->warn("Catalog file not found: {$dataFile}");
            $this->command?->warn('Run backend/database/data/knowledge_base_100/generate_knowledge_base.py to create it.');

            return;
        }

        $catalog = json_decode(file_get_contents($dataFile), true, flags: JSON_THROW_ON_ERROR);

        $diseaseCount = 0;
        $diseaseCreated = 0;
        $symptomCount = 0;
        $symptomCreated = 0;

        $this->command?->info('Importing Knowledge Base 100 catalog (inactive, pending clinical review)...');

        foreach ($catalog['diseases'] ?? [] as $entry) {
            $disease = Disease::updateOrCreate(
                ['code' => $entry['disease_code']],
                [
                    'name' => $entry['name_en'],
                    'name_km' => $entry['name_km'] ?: null,
                    'category' => $entry['category'] ?: null,
                    'description' => $entry['description_en'] ?: null,
                    'severity_level' => null,
                    'is_active' => false,
                ],
            );
            $diseaseCount++;
            if ($disease->wasRecentlyCreated) {
                $diseaseCreated++;
            }
        }

        foreach ($catalog['symptoms'] ?? [] as $entry) {
            $symptom = Symptom::updateOrCreate(
                ['code' => $entry['symptom_code']],
                [
                    'name' => $entry['name_en'],
                    'name_km' => $entry['name_km'] ?: null,
                    'description' => $entry['description_en'] ?: null,
                    'is_active' => false,
                ],
            );
            $symptomCount++;
            if ($symptom->wasRecentlyCreated) {
                $symptomCreated++;
            }
        }

        $this->command?->info(
            "Done. Diseases: {$diseaseCount} total ({$diseaseCreated} created), "
            ."symptoms: {$symptomCount} total ({$symptomCreated} created). "
            .'All catalog records are INACTIVE pending clinical review. No rules or mappings were created.'
        );
    }
}
