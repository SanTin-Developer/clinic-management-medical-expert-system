# Knowledge Base Starter (100 diseases)

Bilingual (English / Khmer) starter catalog for the Clinic Management & Medical
Expert System declared in `manifest.json`.

## Package contents
- `diseases_100.csv` — 100 bilingual disease-catalog records (`KB-001`..`KB-100`)
  grouped by body system (respiratory, digestive, cardiovascular/blood, urinary/
  kidney, endocrine/metabolic, neurological/mental, infectious/protropical,
  skin/musculoskeletal, reproductive/eye/ENT, additional_common_conditions).
- `symptoms_dictionary.csv` — 60 bilingual symptom terms (`SYM-001`..`SYM-060`).
- `disease_symptom_mappings_template.csv` — schema for evidence-linked mappings.
- `expert_rules_template.csv` — schema for AND/OR rule groups, evidence, review.
- `sources.json` — reference index.
- `manifest.json` — counts, scope, and status.
- `knowledge_base_starter.json` — combined package (what the seeder imports).
- `generate_knowledge_base.py` — reproducible generator for these artifacts.

## Important safety and quality status
This is a structured starter catalog, **not a clinically validated diagnostic
knowledge base**.
- ICD-11 codes are deliberately blank until each condition is checked against
  the correct ICD-11 entity and release.
- Disease-symptom mappings are not populated: associations must be supported by
  sources and reviewed, not guessed.
- Expert rules are not populated or activated. Do not infer a diagnosis.
- All catalog records have `clinical_use_enabled=false` and a review status that
  requires review; the live-DB seeder inserts them with `is_active=false`.
- Khmer terms are working translations and need a qualified Khmer-speaking
  clinical reviewer. Known rough spots include `Tonsillitis` and the
  `Tension headache` / `Tension-type headache` pair.

## Recommended data workflow
1. Verify disease name and exact ICD-11 code at https://icd.who.int/en/.
2. Add a disease-specific, reliable clinical source (e.g. MedlinePlus, WHO/MoH
   guidance) and record the access/review date.
3. Create symptom mappings only where the source supports the relationship;
   distinguish common, possible, absent, and red-flag findings.
4. Create rule groups with explicit AND/OR semantics. Rules must not treat
   absence of a symptom as proof that a disease is absent.
5. Have a qualified clinician review each rule, severity classification, and
   red-flag behaviour.
6. Add tests for positive, negative, ambiguous, missing-data, and red-flag
   scenarios.
7. Keep clinical-use flags disabled until clinical and safety review is done.

## Live-app integration
`KnowledgeBase100CatalogSeeder` (Laravel) imports these records into the
`diseases`/`symptoms` tables with `is_active=false` (catalog-only, pending
review) keyed on the stable `code` values via `updateOrCreate()`. It never
creates disease-symptom mappings or expert rules.

## Source references
- WHO ICD-11 Browser: https://icd.who.int/en/
- MedlinePlus Health Topics: https://medlineplus.gov/healthtopics.html
- WHO 2025 arboviral guideline: https://www.who.int/publications/i/item/9789240111110
- WHO dengue fact sheet: https://www.who.int/en/news-room/fact-sheets/detail/dengue-and-severe-dengue

This package is for software development and clinical review, not medical advice
or a clinical decision tool.
