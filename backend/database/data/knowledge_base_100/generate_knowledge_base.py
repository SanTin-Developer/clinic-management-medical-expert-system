"""Generate the bilingual (en/km) Knowledge Base Starter (100 diseases / 60 symptoms).

Reproduces the "clinic_knowledge_base_100" starter package used to drive the
KnowledgeBase100CatalogSeeder. Outputs land next to this script:

- diseases_100.csv                        100 catalog records (KB-001..KB-100)
- symptoms_dictionary.csv                 60 symptom terms (SYM-001..SYM-060)
- disease_symptom_mappings_template.csv   empty schema (no fabricated mappings)
- expert_rules_template.csv               empty schema (no fabricated rules)
- sources.json / manifest.json            provenance + counts
- README.md                               safety/quality guidance
- knowledge_base_starter.json             combined package
- knowledge_base_100_starter.zip          distribution archive (build artifact)

IMPORTANT: This package is a structured starter catalog, NOT a clinically
validated diagnostic knowledge base. All records ship with
clinical_use_enabled=false / needs-review status; no disease-symptom mappings
and no rules are asserted because those require evidence and clinical review.
"""

import csv
import json
import os
import zipfile
from datetime import date

OUTDIR = os.path.dirname(os.path.abspath(__file__))

NINE_GROUPS = [
    ("respiratory", [
        ("Common cold", "ជំងឺផ្ដាសាយធម្មត"),
        ("Influenza", "ជំងឺគ្រុនផ្ដាសាយ"),
        ("COVID-19", "ជំងឺកូវីដ-១៩"),
        ("Asthma", "ជំងឺហឺត"),
        ("Pneumonia", "ជំងឺរលាកសួត"),
        ("Acute bronchitis", "ជំងឺរលាកទងសួតស្រួចស្រាវ"),
        ("Chronic obstructive pulmonary disease (COPD)", "ជំងឺស្ទះសួតរ៉ាំរ៉ៃ"),
        ("Tuberculosis", "ជំងឺរបេង"),
        ("Allergic rhinitis", "ជំងឺរលាកច្រមុះដោយអាលែកស៊ី"),
        ("Sinusitis", "ជំងឺរលាកប្រហោងឆ្អឹងច្រមុះ"),
    ]),
    ("digestive", [
        ("Gastroenteritis", "ជំងឺរលាកក្រពះពោះវៀន"),
        ("Gastroesophageal reflux disease (GERD)", "ជំងឺច្រាលអាស៊ីតក្រពះ"),
        ("Gastritis", "ជំងឺរលាកស្រទាប់ក្រពះ"),
        ("Peptic ulcer disease", "ជំងឺដំបៅក្រពះ/ពោះវៀន"),
        ("Irritable bowel syndrome (IBS)", "រោគសញ្ញាពោះវៀនឆាប់រំខាន"),
        ("Constipation", "ជំងឺទល់លាមក"),
        ("Diarrhea", "ជំងឺរាគ"),
        ("Appendicitis", "ជំងឺរលាកខ្នែងពោះវៀន"),
        ("Gallstones", "ជំងឺគ្រួសក្នុងថង់ទឹកប្រមាត់"),
        ("Hepatitis", "ជំងឺរលាកថ្លើម"),
    ]),
    ("cardiovascular_blood", [
        ("Hypertension", "ជំងឺលើសសម្ពាធឈាម"),
        ("Coronary artery disease", "ជំងឺសរសៃឈាមបេះដូង"),
        ("Heart failure", "ជំងឺខ្សោយបេះដូង"),
        ("Atrial fibrillation", "ចង្វាក់បេះដូងមិនទៀងទាត់ប្រភេទ Atrial fibrillation"),
        ("Peripheral artery disease", "ជំងឺសរសៃឈាមអាកទែរចុងដៃជើង"),
        ("Deep vein thrombosis", "ជំងឺកំណកឈាមក្នុងសរសៃវ៉ែនជ្រៅ"),
        ("Pulmonary embolism", "ស្ទះសរសៃឈាមសួតដោយកំណកឈាម"),
        ("Anemia", "ជំងឺខ្វះគ្រាប់ឈាមក្រហម"),
        ("Iron-deficiency anemia", "ជំងឺខ្វះឈាមដោយកង្វះជាតិដែក"),
        ("Dyslipidemia", "ជំងឺខ្លាញ់ក្នុងឈាមមិនប្រក្រតី"),
    ]),
    ("urinary_kidney", [
        ("Urinary tract infection (UTI)", "ជំងឺឆ្លងផ្លូវទឹកនោម"),
        ("Kidney stones", "ជំងឺគ្រួសក្នុងតម្រងនោម"),
        ("Chronic kidney disease", "ជំងឺតម្រងនោមរ៉ាំរ៉ៃ"),
        ("Acute kidney injury", "ការខូចមុខងារតម្រងនោមស្រួចស្រាវ"),
        ("Pyelonephritis", "ជំងឺរលាកតម្រងនោមដោយការឆ្លង"),
        ("Benign prostatic hyperplasia", "ក្រពេញប្រូស្តាតរីកធំមិនមែនមហារីក"),
        ("Overactive bladder", "ជំងឺប្លោកនោមសកម្មខ្លាំង"),
        ("Urinary incontinence", "ជំងឺនោមទប់មិនបាន"),
        ("Glomerulonephritis", "ជំងឺរលាកតម្រងនោមផ្នែក Glomeruli"),
        ("Nephrotic syndrome", "រោគសញ្ញាបាត់បង់ប្រូតេអ៊ីនតាមទឹកនោម"),
    ]),
    ("endocrine_metabolic", [
        ("Type 1 diabetes mellitus", "ជំងឺទឹកនោមផ្អែមប្រភេទទី១"),
        ("Type 2 diabetes mellitus", "ជំងឺទឹកនោមផ្អែមប្រភេទទី២"),
        ("Hypothyroidism", "ជំងឺក្រពេញទីរ៉ូអ៊ីតធ្វើការថយចុះ"),
        ("Hyperthyroidism", "ជំងឺក្រពេញទីរ៉ូអ៊ីតធ្វើការខ្លាំង"),
        ("Obesity", "ជំងឺធាត់"),
        ("Metabolic syndrome", "រោគសញ្ញាមេតាបូលិច"),
        ("Gout", "ជំងឺរលាកសន្លាក់ដោយគ្រីស្តាល់អាស៊ីតអ៊ុយរិក"),
        ("Vitamin D deficiency", "កង្វះវីតាមីន D"),
        ("Vitamin B12 deficiency", "កង្វះវីតាមីន B12"),
        ("Dehydration", "ជំងឺខ្វះជាតិទឹក"),
    ]),
    ("neurological_mental", [
        ("Migraine", "ជំងឺឈឺក្បាលប្រកាំង"),
        ("Tension-type headache", "ឈឺក្បាលប្រភេទតានតឹង"),
        ("Epilepsy", "ជំងឺឆ្កួតជ្រូក"),
        ("Stroke", "ជំងឺដាច់សរសៃឈាមខួរក្បាល"),
        ("Parkinson disease", "ជំងឺផាកឃីនសុន"),
        ("Alzheimer disease", "ជំងឺអាល់ហ្សៃមឺរ"),
        ("Peripheral neuropathy", "ជំងឺសរសៃប្រសាទចុងដៃជើង"),
        ("Bell palsy", "ជំងឺខ្សោយសាច់ដុំមុខ Bell palsy"),
        ("Major depressive disorder", "ជំងឺធ្លាក់ទឹកចិត្តធ្ងន់ធ្ងរ"),
        ("Generalized anxiety disorder", "ជំងឺថប់បារម្ភទូទៅ"),
    ]),
    ("infectious_tropical", [
        ("Dengue", "ជំងឺគ្រុនឈាម"),
        ("Malaria", "ជំងឺគ្រុនចាញ់"),
        ("Chikungunya", "ជំងឺឈីកុងហ្គុនយ៉ា"),
        ("Zika virus disease", "ជំងឺវីរុសហ្ស៊ីកា"),
        ("Typhoid fever", "ជំងឺគ្រុនពោះវៀន"),
        ("Cholera", "ជំងឺអាសន្នរោគ"),
        ("HIV infection", "ការឆ្លងមេរោគអេដស៍"),
        ("Seasonal influenza", "ជំងឺគ្រុនផ្ដាសាយតាមរដូវ"),
        ("Measles", "ជំងឺកញ្ជ្រឹល"),
        ("Varicella (chickenpox)", "ជំងឺអុតស្វាយ"),
    ]),
    ("skin_musculoskeletal", [
        ("Atopic dermatitis", "ជំងឺរលាកស្បែកអាតូពី"),
        ("Contact dermatitis", "ជំងឺរលាកស្បែកដោយការប៉ះពាល់"),
        ("Psoriasis", "ជំងឺស្បែកស្ងួតក្រាស់ Psoriasis"),
        ("Acne vulgaris", "ជំងឺមុន"),
        ("Cellulitis", "ជំងឺរលាកជាលិកាក្រោមស្បែក"),
        ("Tinea (fungal skin infection)", "ជំងឺស្បែកឆ្លងផ្សិត"),
        ("Osteoarthritis", "ជំងឺរលាកសន្លាក់ដោយការពុកផុយ"),
        ("Rheumatoid arthritis", "ជំងឺរលាកសន្លាក់រ៉ាំរ៉ៃ"),
        ("Low back pain", "ការឈឺចង្កេះ"),
        ("Osteoporosis", "ជំងឺពុកឆ្អឹង"),
    ]),
    ("reproductive_eye_ent", [
        ("Iron-deficiency in pregnancy", "កង្វះជាតិដែកអំឡុងពេលមានផ្ទៃពោះ"),
        ("Endometriosis", "ជំងឺស្រទាប់ស្បូនដុះខុសទីតាំង"),
        ("Polycystic ovary syndrome (PCOS)", "រោគសញ្ញាអូវែពហុគីស"),
        ("Pelvic inflammatory disease", "ជំងឺរលាកអាងត្រគាក"),
        ("Vaginal candidiasis", "ជំងឺផ្សិត Candida ក្នុងទ្វារមាស"),
        ("Benign breast lump", "ដុំសុដន់មិនមែនមហារីក"),
        ("Conjunctivitis", "ជំងឺរលាកស្រោមភ្នែក"),
        ("Cataract", "ជំងឺភ្នែកឡើងបាយ"),
        ("Glaucoma", "ជំងឺដក់ទឹកក្នុងភ្នែក"),
        ("Otitis media", "ជំងឺរលាកត្រចៀកកណ្ដាល"),
    ]),
]

# Tenth group ("additional_common_conditions") to reach the requested 100.
TENTH_GROUP = (
    "additional_common_conditions",
    [
        ("Otitis externa", "ជំងឺរលាកត្រចៀកខាងក្រៅ"),
        ("Pharyngitis", "ជំងឺរលាកបំពង់ក"),
        ("Tonsillitis", "ជំងឺរលាកបំពង់ក tonsils"),
        ("Obstructive sleep apnea", "ជំងឺដង្ហើមផ្អាកពេលគេង"),
        ("Chronic sinusitis", "ជំងឺរលាកប្រហោងឆ្អឹងច្រមុះរ៉ាំរ៉ៃ"),
        ("Urticaria (hives)", "ជំងឺកន្ទាលត្រអាក"),
        ("Scabies", "ជំងឺកមរមាស់"),
        ("Impetigo", "ជំងឺស្បែកឆ្លងបាក់តេរី Impetigo"),
        ("Tension headache", "ឈឺក្បាលប្រភេទតានតឹង"),
        ("Gastrointestinal food poisoning", "ការពុលអាហារ"),
    ],
)

GROUPS = NINE_GROUPS + [TENTH_GROUP]

SYMPTOM_ROWS = [
    ("SYM-001", "Fever", "គ្រុនក្តៅ"),
    ("SYM-002", "Cough", "ក្អក"),
    ("SYM-003", "Shortness of breath", "ពិបាកដកដង្ហើម"),
    ("SYM-004", "Sore throat", "ឈឺបំពង់ក"),
    ("SYM-005", "Runny or blocked nose", "ហៀរសំបោរ ឬតឹងច្រមុះ"),
    ("SYM-006", "Headache", "ឈឺក្បាល"),
    ("SYM-007", "Dizziness", "វិលមុខ"),
    ("SYM-008", "Fatigue", "អស់កម្លាំង"),
    ("SYM-009", "Muscle pain", "ឈឺសាច់ដុំ"),
    ("SYM-010", "Joint pain", "ឈឺសន្លាក់"),
    ("SYM-011", "Nausea", "ចង្អោរ"),
    ("SYM-012", "Vomiting", "ក្អួត"),
    ("SYM-013", "Diarrhea", "រាគ"),
    ("SYM-014", "Constipation", "ទល់លាមក"),
    ("SYM-015", "Abdominal pain", "ឈឺពោះ"),
    ("SYM-016", "Chest pain", "ឈឺទ្រូង"),
    ("SYM-017", "Palpitations", "បេះដូងលោតញាប់ ឬញ័រ"),
    ("SYM-018", "Swelling / edema", "ហើម"),
    ("SYM-019", "Excessive thirst", "ស្រេកទឹកខ្លាំង"),
    ("SYM-020", "Frequent urination", "នោមញឹក"),
    ("SYM-021", "Painful urination", "ឈឺពេលនោម"),
    ("SYM-022", "Blood in urine", "មានឈាមក្នុងទឹកនោម"),
    ("SYM-023", "Back or flank pain", "ឈឺខ្នង ឬចំហៀងចង្កេះ"),
    ("SYM-024", "Rash", "កន្ទួលលើស្បែក"),
    ("SYM-025", "Itching", "រមាស់"),
    ("SYM-026", "Red eye", "ភ្នែកក្រហម"),
    ("SYM-027", "Blurred vision", "មើលមិនច្បាស់"),
    ("SYM-028", "Ear pain", "ឈឺត្រចៀក"),
    ("SYM-029", "Hearing difficulty", "ពិបាកស្តាប់"),
    ("SYM-030", "Numbness or tingling", "ស្ពឹក ឬចាក់ៗ"),
    ("SYM-031", "Weakness", "ខ្សោយ"),
    ("SYM-032", "Confusion or altered mental status", "វង្វេង ឬស្មារតីប្រែប្រួល"),
    ("SYM-033", "Seizure", "ប្រកាច់"),
    ("SYM-034", "One-sided weakness", "ខ្សោយម្ខាងនៃរាងកាយ"),
    ("SYM-035", "Unintentional weight loss", "ស្រកទម្ងន់ដោយមិនបានចេតនា"),
    ("SYM-036", "Weight gain", "ឡើងទម្ងន់"),
    ("SYM-037", "Heat intolerance", "មិនអាចទ្រាំកម្ដៅបាន"),
    ("SYM-038", "Cold intolerance", "មិនអាចទ្រាំត្រជាក់បាន"),
    ("SYM-039", "Excessive sweating", "បែកញើសខ្លាំង"),
    ("SYM-040", "Sleep disturbance", "បញ្ហាដំណេក"),
    ("SYM-041", "Low mood", "អារម្មណ៍សោកសៅ"),
    ("SYM-042", "Anxiety or excessive worry", "ថប់បារម្ភ ឬព្រួយបារម្ភខ្លាំង"),
    ("SYM-043", "Menstrual irregularity", "រដូវមិនទៀងទាត់"),
    ("SYM-044", "Pelvic pain", "ឈឺអាងត្រគាក"),
    ("SYM-045", "Vaginal discharge", "មានទឹករំអិលចេញពីទ្វារមាស"),
    ("SYM-046", "Bleeding", "ហូរឈាម"),
    ("SYM-047", "Pale skin", "ស្បែកស្លេក"),
    ("SYM-048", "Jaundice", "ស្បែក ឬភ្នែកលឿង"),
    ("SYM-049", "Persistent vomiting", "ក្អួតមិនឈប់"),
    ("SYM-050", "Severe abdominal pain", "ឈឺពោះខ្លាំង"),
    ("SYM-051", "Pain behind the eyes", "ឈឺក្រោយភ្នែក"),
    ("SYM-052", "Nosebleed or gum bleeding", "ហូរឈាមច្រមុះ ឬអញ្ចាញធ្មេញ"),
    ("SYM-053", "Rapid breathing", "ដកដង្ហើមញាប់"),
    ("SYM-054", "Cold clammy skin", "ស្បែកត្រជាក់ និងសើម"),
    ("SYM-055", "Fainting", "សន្លប់"),
    ("SYM-056", "Neck stiffness", "ករឹង"),
    ("SYM-057", "Light sensitivity", "ខ្លាចពន្លឺ"),
    ("SYM-058", "Difficulty swallowing", "ពិបាកលេប"),
    ("SYM-059", "Loss of appetite", "បាត់បង់ចំណង់អាហារ"),
    ("SYM-060", "Skin lesion or ulcer", "ដំបៅ ឬស្នាមខូចលើស្បែក"),
]

SOURCES = [
    {
        "source_id": "SRC-WHO-ICD11",
        "organization": "World Health Organization",
        "title": "ICD-11 Browser / Classification",
        "url": "https://icd.who.int/en/",
        "use": "Disease identity and classification; verify exact code per disease.",
        "review_status": "reference_index",
    },
    {
        "source_id": "SRC-MEDLINEPLUS",
        "organization": "U.S. National Library of Medicine",
        "title": "MedlinePlus Health Topics",
        "url": "https://medlineplus.gov/healthtopics.html",
        "use": "General health topic background and symptom information; capture a disease-specific page and review date for each claim.",
        "review_status": "reference_index",
    },
    {
        "source_id": "SRC-WHO-DENGUE-2025",
        "organization": "World Health Organization",
        "title": "WHO guidelines for clinical management of arboviral diseases: dengue, chikungunya, Zika and yellow fever (2025)",
        "url": "https://www.who.int/publications/i/item/9789240111110",
        "use": "Potential disease-specific clinical source for arboviral diseases; clinician must review applicability.",
        "review_status": "reference_index",
    },
    {
        "source_id": "SRC-WHO-DENGUE-FACTSHEET",
        "organization": "World Health Organization",
        "title": "Dengue and severe dengue fact sheet",
        "url": "https://www.who.int/en/news-room/fact-sheets/detail/dengue-and-severe-dengue",
        "use": "Disease-specific background and warning signs for dengue; not a standalone diagnostic rule.",
        "review_status": "reference_index",
    },
]


def build_diseases():
    diseases = []
    n = 1
    for category, items in GROUPS:
        for en, kh in items:
            diseases.append({
                "disease_code": f"KB-{n:03d}",
                "name_en": en,
                "name_km": kh,
                "category": category,
                "description_en": "",
                "description_km": "",
                "icd11_code": "",
                "source_url": "https://icd.who.int/en/",
                "source_note": "Confirm exact ICD-11 entity/code and disease-specific clinical source before clinical use.",
                "review_status": "catalog_needs_review",
                "clinical_use_enabled": False,
            })
            n += 1
    return diseases, n - 1


def build_symptoms():
    return [
        {
            "symptom_code": code,
            "name_en": en,
            "name_km": kh,
            "description_en": "",
            "description_km": "",
            "review_status": "terminology_needs_review",
            "clinical_use_enabled": False,
        }
        for code, en, kh in SYMPTOM_ROWS
    ]


def write_csv(filename, rows, fields):
    with open(os.path.join(OUTDIR, filename), "w", newline="", encoding="utf-8-sig") as f:
        writer = csv.DictWriter(f, fieldnames=fields)
        writer.writeheader()
        writer.writerows(rows)


def main():
    diseases, disease_count = build_diseases()
    symptoms = build_symptoms()

    assert disease_count == 100, disease_count
    assert len(symptoms) == 60, len(symptoms)

    # Templates intentionally empty: no fabricated mappings/rules.
    mappings = []
    rules = []

    manifest = {
        "package_name": "Clinic Management & Medical Expert System - Knowledge Base Starter",
        "version": "0.1.0",
        "created_date": str(date.today()),
        "language": ["en", "km"],
        "target_disease_count": 100,
        "disease_count": len(diseases),
        "symptom_term_count": len(symptoms),
        "disease_symptom_mapping_count": 0,
        "expert_rule_count": 0,
        "status": "structured_starter_not_clinically_validated",
        "important_note": (
            "Disease catalog entries and symptom vocabulary are a bilingual starter index. "
            "No disease-symptom mappings or diagnostic rules are activated because those "
            "require disease-specific evidence and clinical review. "
            "Do not use for diagnosis or treatment."
        ),
        "recommended_workflow": [
            "verify exact ICD-11 code and official title",
            "attach disease-specific source URLs and access/review dates",
            "review Khmer terminology",
            "create evidence-linked symptom mappings",
            "write rule with inclusion/exclusion and safety conditions",
            "clinician review",
            "automated test cases",
            "only then enable clinical use",
        ],
        "sources": SOURCES,
    }

    write_csv("diseases_100.csv", diseases, list(diseases[0].keys()))
    write_csv("symptoms_dictionary.csv", symptoms, list(symptoms[0].keys()))
    write_csv("disease_symptom_mappings_template.csv", [
        {
            "disease_code": "",
            "symptom_code": "",
            "relationship": "supports|against|red_flag",
            "importance": "",
            "evidence_source_id": "",
            "evidence_quote_or_summary": "",
            "review_status": "needs_clinical_review",
            "reviewed_by": "",
            "reviewed_at": "",
        }
    ], ["disease_code", "symptom_code", "relationship", "importance",
        "evidence_source_id", "evidence_quote_or_summary",
        "review_status", "reviewed_by", "reviewed_at"])
    write_csv("expert_rules_template.csv", [
        {
            "rule_code": "",
            "disease_code": "",
            "group_id": "",
            "group_operator": "AND",
            "conditions_json": "[]",
            "conclusion_json": "{}",
            "evidence_source_id": "",
            "rationale_en": "",
            "rationale_km": "",
            "review_status": "draft_needs_clinical_review",
            "approved_by": "",
            "approved_at": "",
            "clinical_use_enabled": "false",
        }
    ], ["rule_code", "disease_code", "group_id", "group_operator", "conditions_json",
        "conclusion_json", "evidence_source_id", "rationale_en", "rationale_km",
        "review_status", "approved_by", "approved_at", "clinical_use_enabled"])

    with open(os.path.join(OUTDIR, "sources.json"), "w", encoding="utf-8") as f:
        json.dump(SOURCES, f, ensure_ascii=False, indent=2)
    with open(os.path.join(OUTDIR, "manifest.json"), "w", encoding="utf-8") as f:
        json.dump(manifest, f, ensure_ascii=False, indent=2)

    readme_path = os.path.join(OUTDIR, "README.md")
    with open(readme_path, "w", encoding="utf-8") as f:
        f.write(README_TEXT)

    starter = {
        "manifest": manifest,
        "diseases": diseases,
        "symptoms": symptoms,
        "disease_symptom_mappings": mappings,
        "expert_rules": rules,
    }
    with open(os.path.join(OUTDIR, "knowledge_base_starter.json"), "w", encoding="utf-8") as f:
        json.dump(starter, f, ensure_ascii=False, indent=2)

    zip_path = os.path.join(OUTDIR, "knowledge_base_100_starter.zip")
    with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED) as z:
        for fn in sorted(os.listdir(OUTDIR)):
            if fn.endswith(".zip") or fn == os.path.basename(__file__):
                continue
            z.write(os.path.join(OUTDIR, fn), arcname=f"clinic_knowledge_base_100/{fn}")

    print("Created:", zip_path)
    print("Diseases:", len(diseases), "| Symptoms:", len(symptoms),
          "| Verified mappings/rules:", len(mappings), len(rules))


README_TEXT = """# Knowledge Base Starter (100 diseases)

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
"""


if __name__ == "__main__":
    main()