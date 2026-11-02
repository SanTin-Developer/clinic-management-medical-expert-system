<?php

namespace Database\Seeders;

use App\Models\Disease;
use App\Models\ExpertRule;
use App\Models\ExpertRuleCondition;
use App\Models\Symptom;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KnowledgeBaseSeeder extends Seeder
{
    /**
     * The knowledge base holds ~100 records, but Supabase is reached through a
     * high-latency session pooler: a row-by-row `updateOrCreate()` loop costs
     * hundreds of round-trips and can outlive the whole Render deploy window.
     * Every write below is therefore set-based (`upsert`/`insert`), which keeps
     * a full re-seed down to a handful of statements.
     *
     * There is deliberately no wrapping transaction. Each step is idempotent
     * (keyed by the unique `code` column) and rebuilds its own links, so an
     * interrupted run resumes from where it stopped on the next boot instead
     * of rolling back several minutes of work.
     */
    public function run(): void
    {
        $this->upsertSymptoms();
        $this->upsertDiseases();
        $this->upsertRules();
        $this->prunePlaceholders();
    }

    private function upsertSymptoms(): void
    {
        $now = now();
        $definitions = $this->symptomDefinitions();

        Symptom::upsert(
            array_map(fn (array $definition): array => [
                'code' => $definition['code'],
                'name' => $definition['name'],
                'description' => $definition['description'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ], $definitions),
            ['code'],
            ['name', 'description', 'is_active', 'updated_at']
        );

        $this->command?->info(sprintf('Knowledge base: %d symptoms upserted.', count($definitions)));
    }

    private function upsertDiseases(): void
    {
        $now = now();
        $definitions = $this->diseaseDefinitions();

        Disease::upsert(
            array_map(fn (array $definition): array => [
                'code' => $definition['code'],
                'name' => $definition['name'],
                'description' => $definition['description'],
                'severity_level' => $definition['severity_level'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ], $definitions),
            ['code'],
            ['name', 'description', 'severity_level', 'is_active', 'updated_at']
        );

        $diseaseIds = Disease::whereIn('code', array_column($definitions, 'code'))
            ->pluck('id', 'code');
        $symptomIds = Symptom::pluck('id', 'code');

        // Rebuild the disease <-> symptom links for the seeded diseases. This
        // mirrors the previous per-disease sync() but in two statements.
        DB::table('disease_symptom')
            ->whereIn('disease_id', $diseaseIds->values())
            ->delete();

        $links = [];

        foreach ($definitions as $definition) {
            $diseaseId = $diseaseIds[$definition['code']];

            foreach ($definition['symptoms'] as $symptomCode) {
                if (! isset($symptomIds[$symptomCode])) {
                    continue;
                }

                $links[] = [
                    'disease_id' => $diseaseId,
                    'symptom_id' => $symptomIds[$symptomCode],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($links, 500) as $chunk) {
            DB::table('disease_symptom')->insert($chunk);
        }

        $this->command?->info(sprintf('Knowledge base: %d diseases upserted.', count($definitions)));
    }

    private function upsertRules(): void
    {
        $now = now();
        $definitions = $this->ruleDefinitions();

        $diseaseIds = Disease::whereIn('code', array_unique(array_column($definitions, 'disease_code')))
            ->pluck('id', 'code');

        ExpertRule::upsert(
            array_map(fn (array $definition): array => [
                'code' => $definition['code'],
                'name' => $definition['name'],
                'disease_id' => $diseaseIds[$definition['disease_code']],
                'explanation' => $definition['explanation'],
                'priority' => $definition['priority'],
                'confidence_score' => $definition['confidence_score'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ], $definitions),
            ['code'],
            ['name', 'disease_id', 'explanation', 'priority', 'confidence_score', 'is_active', 'updated_at']
        );

        $ruleIds = ExpertRule::whereIn('code', array_column($definitions, 'code'))
            ->pluck('id', 'code');
        $symptomIds = Symptom::pluck('id', 'code');

        ExpertRuleCondition::whereIn('expert_rule_id', $ruleIds->values())->delete();

        $conditions = [];

        foreach ($definitions as $definition) {
            $ruleId = $ruleIds[$definition['code']];

            foreach ($definition['groups'] as $groupNumber => $group) {
                foreach ($group as $condition) {
                    if (! isset($symptomIds[$condition['symptom_code']])) {
                        continue;
                    }

                    $conditions[] = [
                        'expert_rule_id' => $ruleId,
                        'symptom_id' => $symptomIds[$condition['symptom_code']],
                        'operator' => $condition['operator'],
                        'expected_value' => $condition['expected_value'],
                        'condition_group' => $groupNumber,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        foreach (array_chunk($conditions, 500) as $chunk) {
            ExpertRuleCondition::insert($chunk);
        }

        $this->command?->info(sprintf('Knowledge base: %d expert rules upserted.', count($definitions)));
    }

    private function prunePlaceholders(): void
    {
        $placeholderDiseaseIds = Disease::whereIn(
            'code',
            ['DIS-ILLNESS-A', 'DIS-ILLNESS-B']
        )->pluck('id');

        ExpertRule::whereIn('code', ['RULE-001', 'RULE-002'])->delete();
        ExpertRule::whereIn('disease_id', $placeholderDiseaseIds)->delete();
        Disease::whereIn('id', $placeholderDiseaseIds)->delete();
    }

    private function present(string $symptomCode): array
    {
        return [
            'symptom_code' => $symptomCode,
            'operator' => 'present',
            'expected_value' => true,
        ];
    }

    private function absent(string $symptomCode): array
    {
        return [
            'symptom_code' => $symptomCode,
            'operator' => 'absent',
            'expected_value' => true,
        ];
    }

    private function symptomDefinitions(): array
    {
        return [
            ['code' => 'SYM-FEVER', 'name' => 'Fever', 'description' => 'Elevated body temperature.'],
            ['code' => 'SYM-HIGH-FEVER', 'name' => 'High-grade fever', 'description' => 'Temperature generally above 39 C.'],
            ['code' => 'SYM-CHILLS', 'name' => 'Chills', 'description' => 'Sensation of cold with shivering.'],
            ['code' => 'SYM-FATIGUE', 'name' => 'Fatigue', 'description' => 'Persistent tiredness or low energy.'],
            ['code' => 'SYM-MALAISE', 'name' => 'Malaise', 'description' => 'General feeling of being unwell.'],
            ['code' => 'SYM-BODY-ACHE', 'name' => 'Body aches', 'description' => 'Diffuse bodily discomfort.'],
            ['code' => 'SYM-JOINT-PAIN', 'name' => 'Joint pain', 'description' => 'Pain in one or more joints.'],
            ['code' => 'SYM-MUSCLE-PAIN', 'name' => 'Muscle pain', 'description' => 'Aching in the muscles.'],
            ['code' => 'SYM-HEADACHE', 'name' => 'Headache', 'description' => 'Pain or discomfort in the head.'],
            ['code' => 'SYM-THROBBING-HEADACHE', 'name' => 'Throbbing headache', 'description' => 'Pulsating one-sided head pain.'],
            ['code' => 'SYM-BAND-HEADACHE', 'name' => 'Band-like headache', 'description' => 'Bilateral pressing head pain.'],
            ['code' => 'SYM-PHOTOPHOBIA', 'name' => 'Light sensitivity', 'description' => 'Discomfort from light.'],
            ['code' => 'SYM-NAUSEA', 'name' => 'Nausea', 'description' => 'Feeling of needing to vomit.'],
            ['code' => 'SYM-VOMITING', 'name' => 'Vomiting', 'description' => 'Emesis.'],
            ['code' => 'SYM-DIARRHEA', 'name' => 'Diarrhea', 'description' => 'Frequent loose or watery stools.'],
            ['code' => 'SYM-CONSTIPATION', 'name' => 'Constipation', 'description' => 'Infrequent or difficult bowel movements.'],
            ['code' => 'SYM-ABDOMINAL-PAIN', 'name' => 'Abdominal pain', 'description' => 'Pain in the abdomen.'],
            ['code' => 'SYM-EPIGASTRIC-PAIN', 'name' => 'Epigastric pain', 'description' => 'Pain in the upper central abdomen.'],
            ['code' => 'SYM-HEARTBURN', 'name' => 'Heartburn', 'description' => 'Burning behind the breastbone.'],
            ['code' => 'SYM-BLOATING', 'name' => 'Bloating', 'description' => 'Abdominal fullness or distension.'],
            ['code' => 'SYM-BLOOD-IN-STOOL', 'name' => 'Blood in stool', 'description' => 'Visible blood in bowel movements.'],
            ['code' => 'SYM-BLACK-STOOL', 'name' => 'Black tarry stool', 'description' => 'Melena or tarry stool.'],
            ['code' => 'SYM-COUGH', 'name' => 'Cough', 'description' => 'Cough of any type.'],
            ['code' => 'SYM-DRY-COUGH', 'name' => 'Dry cough', 'description' => 'Non-productive cough.'],
            ['code' => 'SYM-PRODUCTIVE-COUGH', 'name' => 'Productive cough', 'description' => 'Cough with sputum.'],
            ['code' => 'SYM-COUGH-BLOOD', 'name' => 'Coughing up blood', 'description' => 'Haemoptysis.'],
            ['code' => 'SYM-SORE-THROAT', 'name' => 'Sore throat', 'description' => 'Pain in the throat.'],
            ['code' => 'SYM-RUNNY-NOSE', 'name' => 'Runny nose', 'description' => 'Nasal discharge.'],
            ['code' => 'SYM-NASAL-CONGESTION', 'name' => 'Nasal congestion', 'description' => 'Blocked nose.'],
            ['code' => 'SYM-SNEEZING', 'name' => 'Sneezing', 'description' => 'Frequent sneezing.'],
            ['code' => 'SYM-ITCHY-EYES', 'name' => 'Itchy eyes', 'description' => 'Ocular itching.'],
            ['code' => 'SYM-ITCHY-NOSE', 'name' => 'Itchy nose', 'description' => 'Nasal itching.'],
            ['code' => 'SYM-SHORTNESS-OF-BREATH', 'name' => 'Shortness of breath', 'description' => 'Difficulty breathing.'],
            ['code' => 'SYM-WHEEZING', 'name' => 'Wheezing', 'description' => 'Whistling sound while breathing.'],
            ['code' => 'SYM-CHEST-TIGHTNESS', 'name' => 'Chest tightness', 'description' => 'Pressure or tightness in the chest.'],
            ['code' => 'SYM-CHEST-PAIN-BREATHING', 'name' => 'Chest pain on breathing', 'description' => 'Pleuritic chest pain.'],
            ['code' => 'SYM-LOSS-OF-SMELL', 'name' => 'Loss of smell', 'description' => 'Anosmia.'],
            ['code' => 'SYM-LOSS-OF-TASTE', 'name' => 'Loss of taste', 'description' => 'Ageusia.'],
            ['code' => 'SYM-HOARSENESS', 'name' => 'Hoarseness', 'description' => 'Raspy or strained voice.'],
            ['code' => 'SYM-DIZZINESS', 'name' => 'Dizziness', 'description' => 'Lightheadedness or unsteadiness.'],
            ['code' => 'SYM-BLURRED-VISION', 'name' => 'Blurred vision', 'description' => 'Reduced clarity of vision.'],
            ['code' => 'SYM-EXCESSIVE-THIRST', 'name' => 'Excessive thirst', 'description' => 'Persistent increased thirst.'],
            ['code' => 'SYM-FREQUENT-URINATION', 'name' => 'Frequent urination', 'description' => 'Urinating more often than usual.'],
            ['code' => 'SYM-BURNING-URINATION', 'name' => 'Burning urination', 'description' => 'Pain or burning when urinating.'],
            ['code' => 'SYM-URGENCY', 'name' => 'Urinary urgency', 'description' => 'Sudden urge to urinate.'],
            ['code' => 'SYM-FLANK-PAIN', 'name' => 'Flank pain', 'description' => 'Pain in the side or back.'],
            ['code' => 'SYM-BLOOD-IN-URINE', 'name' => 'Blood in urine', 'description' => 'Haematuria.'],
            ['code' => 'SYM-RASH', 'name' => 'Skin rash', 'description' => 'Abnormal skin eruption.'],
            ['code' => 'SYM-RETROORBITAL-PAIN', 'name' => 'Pain behind the eyes', 'description' => 'Retro-orbital pain.'],
            ['code' => 'SYM-BLEEDING-GUMS', 'name' => 'Bleeding gums', 'description' => 'Gum bleeding.'],
            ['code' => 'SYM-NIGHT-SWEATS', 'name' => 'Night sweats', 'description' => 'Profuse sweating at night.'],
            ['code' => 'SYM-WEIGHT-LOSS', 'name' => 'Weight loss', 'description' => 'Unintentional loss of weight.'],
            ['code' => 'SYM-WEIGHT-GAIN', 'name' => 'Weight gain', 'description' => 'Unintentional gain of weight.'],
            ['code' => 'SYM-COLD-INTOLERANCE', 'name' => 'Cold intolerance', 'description' => 'Abnormal sensitivity to cold.'],
            ['code' => 'SYM-PALPITATIONS', 'name' => 'Palpitations', 'description' => 'Awareness of a rapid heartbeat.'],
            ['code' => 'SYM-SLOW-HEALING-WOUNDS', 'name' => 'Slow-healing wounds', 'description' => 'Wounds that heal slowly.'],
            ['code' => 'SYM-NUMBNESS-FEET', 'name' => 'Numbness in feet', 'description' => 'Reduced sensation in the feet.'],
            ['code' => 'SYM-PALLOR', 'name' => 'Pallor', 'description' => 'Unusual paleness of the skin.'],
            ['code' => 'SYM-LOSS-OF-APPETITE', 'name' => 'Loss of appetite', 'description' => 'Reduced desire to eat.'],
            ['code' => 'SYM-SWEATING', 'name' => 'Excessive sweating', 'description' => 'Profuse sweating.'],
            ['code' => 'SYM-NECK-STIFFNESS', 'name' => 'Neck stiffness', 'description' => 'Difficulty moving the neck.'],
            ['code' => 'SYM-PELVIC-PAIN', 'name' => 'Pelvic pain', 'description' => 'Pain in the lower pelvis.'],
            ['code' => 'SYM-FACIAL-PAIN', 'name' => 'Facial pain', 'description' => 'Pain over the face.'],
        ];
    }

    private function diseaseDefinitions(): array
    {
        return [
            [
                'code' => 'DIS-COMMON-COLD',
                'name' => 'Common Cold',
                'description' => 'Viral upper respiratory tract infection.',
                'severity_level' => 'mild',
                'symptoms' => [
                    'SYM-RUNNY-NOSE', 'SYM-NASAL-CONGESTION', 'SYM-SNEEZING',
                    'SYM-SORE-THROAT', 'SYM-DRY-COUGH', 'SYM-HEADACHE',
                    'SYM-MALAISE', 'SYM-FEVER',
                ],
            ],
            [
                'code' => 'DIS-INFLUENZA',
                'name' => 'Influenza',
                'description' => 'Seasonal influenza virus infection.',
                'severity_level' => 'moderate',
                'symptoms' => [
                    'SYM-HIGH-FEVER', 'SYM-CHILLS', 'SYM-BODY-ACHE',
                    'SYM-FATIGUE', 'SYM-DRY-COUGH', 'SYM-HEADACHE',
                    'SYM-SORE-THROAT', 'SYM-MUSCLE-PAIN',
                ],
            ],
            [
                'code' => 'DIS-COVID19',
                'name' => 'COVID-19',
                'description' => 'SARS-CoV-2 respiratory infection.',
                'severity_level' => 'moderate',
                'symptoms' => [
                    'SYM-FEVER', 'SYM-DRY-COUGH', 'SYM-LOSS-OF-SMELL',
                    'SYM-LOSS-OF-TASTE', 'SYM-FATIGUE', 'SYM-SORE-THROAT',
                    'SYM-SHORTNESS-OF-BREATH', 'SYM-HEADACHE',
                ],
            ],
            [
                'code' => 'DIS-STREP-PHARYNGITIS',
                'name' => 'Streptococcal Pharyngitis',
                'description' => 'Group A streptococcal throat infection.',
                'severity_level' => 'moderate',
                'symptoms' => [
                    'SYM-SORE-THROAT', 'SYM-FEVER', 'SYM-HEADACHE',
                    'SYM-NAUSEA', 'SYM-COUGH',
                ],
            ],
            [
                'code' => 'DIS-ACUTE-BRONCHITIS',
                'name' => 'Acute Bronchitis',
                'description' => 'Inflammation of the bronchi, often after a viral illness.',
                'severity_level' => 'mild',
                'symptoms' => [
                    'SYM-PRODUCTIVE-COUGH', 'SYM-CHEST-TIGHTNESS', 'SYM-WHEEZING',
                    'SYM-SHORTNESS-OF-BREATH', 'SYM-SORE-THROAT', 'SYM-FEVER',
                ],
            ],
            [
                'code' => 'DIS-PNEUMONIA',
                'name' => 'Community-Acquired Pneumonia',
                'description' => 'Lower respiratory tract infection affecting the lung tissue.',
                'severity_level' => 'severe',
                'symptoms' => [
                    'SYM-HIGH-FEVER', 'SYM-PRODUCTIVE-COUGH', 'SYM-SHORTNESS-OF-BREATH',
                    'SYM-CHEST-PAIN-BREATHING', 'SYM-CHILLS', 'SYM-FATIGUE',
                    'SYM-COUGH-BLOOD',
                ],
            ],
            [
                'code' => 'DIS-ALLERGIC-RHINITIS',
                'name' => 'Allergic Rhinitis',
                'description' => 'IgE-mediated inflammation of the nasal passages.',
                'severity_level' => 'mild',
                'symptoms' => [
                    'SYM-SNEEZING', 'SYM-RUNNY-NOSE', 'SYM-ITCHY-EYES',
                    'SYM-ITCHY-NOSE', 'SYM-NASAL-CONGESTION', 'SYM-FEVER',
                ],
            ],
            [
                'code' => 'DIS-ASTHMA',
                'name' => 'Asthma Exacerbation',
                'description' => 'Acute worsening of reactive airway disease.',
                'severity_level' => 'severe',
                'symptoms' => [
                    'SYM-WHEEZING', 'SYM-SHORTNESS-OF-BREATH',
                    'SYM-CHEST-TIGHTNESS', 'SYM-DRY-COUGH',
                ],
            ],
            [
                'code' => 'DIS-MIGRAINE',
                'name' => 'Migraine',
                'description' => 'Recurrent moderate to severe primary headache.',
                'severity_level' => 'moderate',
                'symptoms' => [
                    'SYM-THROBBING-HEADACHE', 'SYM-PHOTOPHOBIA', 'SYM-NAUSEA',
                    'SYM-VOMITING', 'SYM-HEADACHE', 'SYM-BLURRED-VISION',
                ],
            ],
            [
                'code' => 'DIS-TENSION-HEADACHE',
                'name' => 'Tension-Type Headache',
                'description' => 'Mild bilateral primary headache.',
                'severity_level' => 'mild',
                'symptoms' => [
                    'SYM-BAND-HEADACHE', 'SYM-NECK-STIFFNESS', 'SYM-HEADACHE',
                ],
            ],
            [
                'code' => 'DIS-GASTROENTERITIS',
                'name' => 'Acute Gastroenteritis',
                'description' => 'Infectious diarrhoeal illness.',
                'severity_level' => 'moderate',
                'symptoms' => [
                    'SYM-DIARRHEA', 'SYM-VOMITING', 'SYM-ABDOMINAL-PAIN',
                    'SYM-NAUSEA', 'SYM-LOSS-OF-APPETITE', 'SYM-FEVER',
                ],
            ],
            [
                'code' => 'DIS-GASTRITIS',
                'name' => 'Gastritis and Peptic Ulcer Disease',
                'description' => 'Acid-related upper gastrointestinal disease.',
                'severity_level' => 'moderate',
                'symptoms' => [
                    'SYM-EPIGASTRIC-PAIN', 'SYM-HEARTBURN', 'SYM-BLOATING',
                    'SYM-NAUSEA', 'SYM-BLACK-STOOL', 'SYM-BLOOD-IN-STOOL',
                ],
            ],
            [
                'code' => 'DIS-UTI',
                'name' => 'Urinary Tract Infection',
                'description' => 'Bacterial infection of the urinary tract.',
                'severity_level' => 'moderate',
                'symptoms' => [
                    'SYM-BURNING-URINATION', 'SYM-FREQUENT-URINATION', 'SYM-URGENCY',
                    'SYM-PELVIC-PAIN', 'SYM-BLOOD-IN-URINE', 'SYM-FLANK-PAIN',
                    'SYM-FEVER',
                ],
            ],
            [
                'code' => 'DIS-DENGUE',
                'name' => 'Dengue Fever',
                'description' => 'Mosquito-borne flavivirus infection.',
                'severity_level' => 'severe',
                'symptoms' => [
                    'SYM-HIGH-FEVER', 'SYM-JOINT-PAIN', 'SYM-MUSCLE-PAIN',
                    'SYM-RASH', 'SYM-RETROORBITAL-PAIN', 'SYM-BLEEDING-GUMS',
                    'SYM-HEADACHE', 'SYM-NAUSEA',
                ],
            ],
            [
                'code' => 'DIS-TYPHOID',
                'name' => 'Typhoid Fever',
                'description' => 'Systemic Salmonella Typhi infection.',
                'severity_level' => 'severe',
                'symptoms' => [
                    'SYM-FEVER', 'SYM-ABDOMINAL-PAIN', 'SYM-LOSS-OF-APPETITE',
                    'SYM-NIGHT-SWEATS', 'SYM-CONSTIPATION', 'SYM-DIARRHEA',
                    'SYM-HEADACHE',
                ],
            ],
            [
                'code' => 'DIS-HYPERTENSION',
                'name' => 'Uncontrolled Hypertension',
                'description' => 'Persistently elevated arterial blood pressure.',
                'severity_level' => 'moderate',
                'symptoms' => [
                    'SYM-HEADACHE', 'SYM-DIZZINESS', 'SYM-BLURRED-VISION',
                    'SYM-CHEST-PAIN-BREATHING', 'SYM-PALPITATIONS',
                ],
            ],
            [
                'code' => 'DIS-DIABETES',
                'name' => 'Type 2 Diabetes Mellitus',
                'description' => 'Chronic hyperglycaemia with insulin resistance.',
                'severity_level' => 'moderate',
                'symptoms' => [
                    'SYM-EXCESSIVE-THIRST', 'SYM-FREQUENT-URINATION',
                    'SYM-BLURRED-VISION', 'SYM-SLOW-HEALING-WOUNDS',
                    'SYM-NUMBNESS-FEET', 'SYM-WEIGHT-LOSS', 'SYM-FATIGUE',
                ],
            ],
            [
                'code' => 'DIS-IRON-ANEMIA',
                'name' => 'Iron-Deficiency Anemia',
                'description' => 'Microcytic anemia caused by iron deficiency.',
                'severity_level' => 'mild',
                'symptoms' => [
                    'SYM-FATIGUE', 'SYM-PALLOR', 'SYM-PALPITATIONS',
                    'SYM-SHORTNESS-OF-BREATH', 'SYM-DIZZINESS',
                ],
            ],
            [
                'code' => 'DIS-HYPOTHYROIDISM',
                'name' => 'Hypothyroidism',
                'description' => 'Underactive thyroid gland.',
                'severity_level' => 'moderate',
                'symptoms' => [
                    'SYM-FATIGUE', 'SYM-WEIGHT-GAIN', 'SYM-COLD-INTOLERANCE',
                    'SYM-CONSTIPATION', 'SYM-DIZZINESS',
                ],
            ],
            [
                'code' => 'DIS-MALARIA',
                'name' => 'Malaria',
                'description' => 'Plasmodium infection transmitted by mosquitoes.',
                'severity_level' => 'severe',
                'symptoms' => [
                    'SYM-HIGH-FEVER', 'SYM-CHILLS', 'SYM-SWEATING',
                    'SYM-HEADACHE', 'SYM-MUSCLE-PAIN', 'SYM-NAUSEA',
                    'SYM-VOMITING',
                ],
            ],
        ];
    }

    private function ruleDefinitions(): array
    {
        return [
            [
                'code' => 'RULE-COLD-001',
                'name' => 'Common Cold Pattern',
                'disease_code' => 'DIS-COMMON-COLD',
                'explanation' => 'Runny or blocked nose with sneezing or sore throat suggests a viral upper respiratory infection.',
                'priority' => 5,
                'confidence_score' => 0.70,
                'groups' => [
                    1 => [$this->present('SYM-RUNNY-NOSE'), $this->present('SYM-NASAL-CONGESTION')],
                    2 => [$this->present('SYM-RUNNY-NOSE'), $this->present('SYM-SNEEZING')],
                    3 => [$this->present('SYM-SORE-THROAT'), $this->present('SYM-DRY-COUGH')],
                ],
            ],
            [
                'code' => 'RULE-FLU-001',
                'name' => 'Influenza Pattern',
                'disease_code' => 'DIS-INFLUENZA',
                'explanation' => 'High fever with body aches and a dry cough or chills suggests seasonal influenza.',
                'priority' => 7,
                'confidence_score' => 0.85,
                'groups' => [
                    1 => [$this->present('SYM-HIGH-FEVER'), $this->present('SYM-BODY-ACHE'), $this->present('SYM-DRY-COUGH')],
                    2 => [$this->present('SYM-HIGH-FEVER'), $this->present('SYM-CHILLS'), $this->present('SYM-FATIGUE')],
                    3 => [$this->present('SYM-FEVER'), $this->present('SYM-CHILLS'), $this->present('SYM-BODY-ACHE')],
                ],
            ],
            [
                'code' => 'RULE-COVID-001',
                'name' => 'COVID-19 Pattern',
                'disease_code' => 'DIS-COVID19',
                'explanation' => 'Loss of smell or taste, with or without fever and dry cough, suggests SARS-CoV-2 infection.',
                'priority' => 8,
                'confidence_score' => 0.80,
                'groups' => [
                    1 => [$this->present('SYM-LOSS-OF-SMELL'), $this->present('SYM-LOSS-OF-TASTE')],
                    2 => [$this->present('SYM-FEVER'), $this->present('SYM-DRY-COUGH'), $this->present('SYM-LOSS-OF-SMELL')],
                    3 => [$this->present('SYM-FEVER'), $this->present('SYM-DRY-COUGH'), $this->present('SYM-LOSS-OF-TASTE')],
                ],
            ],
            [
                'code' => 'RULE-STREP-001',
                'name' => 'Streptococcal Pharyngitis Pattern',
                'disease_code' => 'DIS-STREP-PHARYNGITIS',
                'explanation' => 'Sore throat and fever without cough raises suspicion of streptococcal pharyngitis.',
                'priority' => 7,
                'confidence_score' => 0.75,
                'groups' => [
                    1 => [$this->present('SYM-SORE-THROAT'), $this->present('SYM-FEVER'), $this->absent('SYM-COUGH')],
                ],
            ],
            [
                'code' => 'RULE-BRONCH-001',
                'name' => 'Acute Bronchitis Pattern',
                'disease_code' => 'DIS-ACUTE-BRONCHITIS',
                'explanation' => 'Productive cough with chest tightness or wheezing suggests acute bronchitis.',
                'priority' => 6,
                'confidence_score' => 0.70,
                'groups' => [
                    1 => [$this->present('SYM-PRODUCTIVE-COUGH'), $this->present('SYM-CHEST-TIGHTNESS')],
                    2 => [$this->present('SYM-PRODUCTIVE-COUGH'), $this->present('SYM-WHEEZING')],
                ],
            ],
            [
                'code' => 'RULE-PNEUMO-001',
                'name' => 'Pneumonia Pattern',
                'disease_code' => 'DIS-PNEUMONIA',
                'explanation' => 'High fever with productive cough, breathlessness or pleuritic chest pain suggests pneumonia.',
                'priority' => 9,
                'confidence_score' => 0.88,
                'groups' => [
                    1 => [$this->present('SYM-HIGH-FEVER'), $this->present('SYM-PRODUCTIVE-COUGH'), $this->present('SYM-SHORTNESS-OF-BREATH')],
                    2 => [$this->present('SYM-PRODUCTIVE-COUGH'), $this->present('SYM-CHEST-PAIN-BREATHING'), $this->present('SYM-CHILLS')],
                ],
            ],
            [
                'code' => 'RULE-ALLERGY-001',
                'name' => 'Allergic Rhinitis Pattern',
                'disease_code' => 'DIS-ALLERGIC-RHINITIS',
                'explanation' => 'Sneezing with itchy eyes or nose and no fever suggests allergic rhinitis.',
                'priority' => 6,
                'confidence_score' => 0.78,
                'groups' => [
                    1 => [$this->present('SYM-SNEEZING'), $this->present('SYM-ITCHY-EYES'), $this->absent('SYM-FEVER')],
                    2 => [$this->present('SYM-SNEEZING'), $this->present('SYM-ITCHY-NOSE'), $this->present('SYM-RUNNY-NOSE'), $this->absent('SYM-FEVER')],
                ],
            ],
            [
                'code' => 'RULE-ASTHMA-001',
                'name' => 'Asthma Exacerbation Pattern',
                'disease_code' => 'DIS-ASTHMA',
                'explanation' => 'Wheezing with breathlessness and chest tightness suggests an asthma exacerbation.',
                'priority' => 9,
                'confidence_score' => 0.85,
                'groups' => [
                    1 => [$this->present('SYM-WHEEZING'), $this->present('SYM-SHORTNESS-OF-BREATH'), $this->present('SYM-CHEST-TIGHTNESS')],
                    2 => [$this->present('SYM-WHEEZING'), $this->present('SYM-DRY-COUGH'), $this->present('SYM-CHEST-TIGHTNESS')],
                ],
            ],
            [
                'code' => 'RULE-MIGRAINE-001',
                'name' => 'Migraine Pattern',
                'disease_code' => 'DIS-MIGRAINE',
                'explanation' => 'Throbbing headache with light sensitivity and nausea or vomiting suggests migraine.',
                'priority' => 7,
                'confidence_score' => 0.82,
                'groups' => [
                    1 => [$this->present('SYM-THROBBING-HEADACHE'), $this->present('SYM-PHOTOPHOBIA'), $this->present('SYM-NAUSEA')],
                    2 => [$this->present('SYM-THROBBING-HEADACHE'), $this->present('SYM-PHOTOPHOBIA'), $this->present('SYM-VOMITING')],
                ],
            ],
            [
                'code' => 'RULE-TENSION-001',
                'name' => 'Tension-Type Headache Pattern',
                'disease_code' => 'DIS-TENSION-HEADACHE',
                'explanation' => 'Band-like headache with neck stiffness and without throbbing suggests a tension-type headache.',
                'priority' => 4,
                'confidence_score' => 0.65,
                'groups' => [
                    1 => [$this->present('SYM-BAND-HEADACHE'), $this->present('SYM-NECK-STIFFNESS'), $this->absent('SYM-THROBBING-HEADACHE')],
                    2 => [$this->present('SYM-BAND-HEADACHE'), $this->absent('SYM-THROBBING-HEADACHE')],
                ],
            ],
            [
                'code' => 'RULE-GASTRO-001',
                'name' => 'Acute Gastroenteritis Pattern',
                'disease_code' => 'DIS-GASTROENTERITIS',
                'explanation' => 'Diarrhoea with vomiting or abdominal pain suggests acute gastroenteritis.',
                'priority' => 6,
                'confidence_score' => 0.80,
                'groups' => [
                    1 => [$this->present('SYM-DIARRHEA'), $this->present('SYM-VOMITING'), $this->present('SYM-ABDOMINAL-PAIN')],
                    2 => [$this->present('SYM-DIARRHEA'), $this->present('SYM-NAUSEA'), $this->present('SYM-LOSS-OF-APPETITE')],
                ],
            ],
            [
                'code' => 'RULE-GASTRITIS-001',
                'name' => 'Gastritis Pattern',
                'disease_code' => 'DIS-GASTRITIS',
                'explanation' => 'Epigastric pain with heartburn or bloating suggests acid-related disease; black stool indicates possible bleeding.',
                'priority' => 6,
                'confidence_score' => 0.72,
                'groups' => [
                    1 => [$this->present('SYM-EPIGASTRIC-PAIN'), $this->present('SYM-HEARTBURN')],
                    2 => [$this->present('SYM-EPIGASTRIC-PAIN'), $this->present('SYM-BLOATING'), $this->present('SYM-NAUSEA')],
                    3 => [$this->present('SYM-EPIGASTRIC-PAIN'), $this->present('SYM-BLACK-STOOL')],
                ],
            ],
            [
                'code' => 'RULE-UTI-001',
                'name' => 'Urinary Tract Infection Pattern',
                'disease_code' => 'DIS-UTI',
                'explanation' => 'Burning urination with frequency, urgency or blood in urine suggests a urinary tract infection.',
                'priority' => 7,
                'confidence_score' => 0.80,
                'groups' => [
                    1 => [$this->present('SYM-BURNING-URINATION'), $this->present('SYM-FREQUENT-URINATION')],
                    2 => [$this->present('SYM-BURNING-URINATION'), $this->present('SYM-URGENCY')],
                    3 => [$this->present('SYM-BURNING-URINATION'), $this->present('SYM-BLOOD-IN-URINE')],
                ],
            ],
            [
                'code' => 'RULE-DENGUE-001',
                'name' => 'Dengue Pattern',
                'disease_code' => 'DIS-DENGUE',
                'explanation' => 'High fever with joint or muscle pain, rash, retro-orbital pain or bleeding suggests dengue.',
                'priority' => 9,
                'confidence_score' => 0.85,
                'groups' => [
                    1 => [$this->present('SYM-HIGH-FEVER'), $this->present('SYM-JOINT-PAIN'), $this->present('SYM-RASH')],
                    2 => [$this->present('SYM-HIGH-FEVER'), $this->present('SYM-RETROORBITAL-PAIN'), $this->present('SYM-MUSCLE-PAIN')],
                    3 => [$this->present('SYM-HIGH-FEVER'), $this->present('SYM-BLEEDING-GUMS')],
                ],
            ],
            [
                'code' => 'RULE-TYPHOID-001',
                'name' => 'Typhoid Pattern',
                'disease_code' => 'DIS-TYPHOID',
                'explanation' => 'Prolonged fever with abdominal pain, loss of appetite and night sweats suggests enteric fever.',
                'priority' => 8,
                'confidence_score' => 0.70,
                'groups' => [
                    1 => [
                        $this->present('SYM-FEVER'),
                        $this->present('SYM-ABDOMINAL-PAIN'),
                        $this->present('SYM-LOSS-OF-APPETITE'),
                        $this->present('SYM-NIGHT-SWEATS'),
                    ],
                ],
            ],
            [
                'code' => 'RULE-HTN-001',
                'name' => 'Hypertension Pattern',
                'disease_code' => 'DIS-HYPERTENSION',
                'explanation' => 'Headache with dizziness or blurred vision may accompany elevated blood pressure but is nonspecific.',
                'priority' => 3,
                'confidence_score' => 0.55,
                'groups' => [
                    1 => [$this->present('SYM-HEADACHE'), $this->present('SYM-DIZZINESS'), $this->present('SYM-BLURRED-VISION')],
                    2 => [$this->present('SYM-HEADACHE'), $this->present('SYM-CHEST-PAIN-BREATHING'), $this->present('SYM-DIZZINESS')],
                ],
            ],
            [
                'code' => 'RULE-DM2-001',
                'name' => 'Type 2 Diabetes Pattern',
                'disease_code' => 'DIS-DIABETES',
                'explanation' => 'Excessive thirst and frequent urination with blurred vision or slow-healing wounds suggests hyperglycaemia.',
                'priority' => 7,
                'confidence_score' => 0.80,
                'groups' => [
                    1 => [$this->present('SYM-EXCESSIVE-THIRST'), $this->present('SYM-FREQUENT-URINATION'), $this->present('SYM-BLURRED-VISION')],
                    2 => [$this->present('SYM-EXCESSIVE-THIRST'), $this->present('SYM-SLOW-HEALING-WOUNDS'), $this->present('SYM-NUMBNESS-FEET')],
                ],
            ],
            [
                'code' => 'RULE-ANEMIA-001',
                'name' => 'Iron-Deficiency Anemia Pattern',
                'disease_code' => 'DIS-IRON-ANEMIA',
                'explanation' => 'Fatigue and pallor with palpitations or dizziness suggests anemia.',
                'priority' => 5,
                'confidence_score' => 0.72,
                'groups' => [
                    1 => [$this->present('SYM-FATIGUE'), $this->present('SYM-PALLOR'), $this->present('SYM-PALPITATIONS')],
                    2 => [$this->present('SYM-FATIGUE'), $this->present('SYM-PALLOR'), $this->present('SYM-DIZZINESS')],
                ],
            ],
            [
                'code' => 'RULE-HYPO-001',
                'name' => 'Hypothyroidism Pattern',
                'disease_code' => 'DIS-HYPOTHYROIDISM',
                'explanation' => 'Fatigue with weight gain and cold intolerance or constipation suggests hypothyroidism.',
                'priority' => 6,
                'confidence_score' => 0.72,
                'groups' => [
                    1 => [$this->present('SYM-FATIGUE'), $this->present('SYM-WEIGHT-GAIN'), $this->present('SYM-COLD-INTOLERANCE')],
                    2 => [$this->present('SYM-FATIGUE'), $this->present('SYM-CONSTIPATION'), $this->present('SYM-COLD-INTOLERANCE')],
                ],
            ],
            [
                'code' => 'RULE-MALARIA-001',
                'name' => 'Malaria Pattern',
                'disease_code' => 'DIS-MALARIA',
                'explanation' => 'High fever with chills and sweating suggests malaria in an endemic setting.',
                'priority' => 9,
                'confidence_score' => 0.80,
                'groups' => [
                    1 => [$this->present('SYM-HIGH-FEVER'), $this->present('SYM-CHILLS'), $this->present('SYM-SWEATING')],
                    2 => [
                        $this->present('SYM-HIGH-FEVER'),
                        $this->present('SYM-CHILLS'),
                        $this->present('SYM-HEADACHE'),
                        $this->present('SYM-MUSCLE-PAIN'),
                    ],
                ],
            ],
        ];
    }
}
