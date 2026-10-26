<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Diagnosis;
use App\Models\Disease;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MedicalRecord;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Staff;
use App\Models\StaffSalaryHistory;
use App\Models\StockMovement;
use App\Models\Symptom;
use App\Models\User;
use App\Models\VitalSign;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Bilingual (Khmer) demo data for the core clinic tables.
 *
 * Display text (names, addresses, complaints, notes, medicine/disease/symptom
 * names) is written in Khmer script; identifiers, statuses and enum codes stay
 * in English so the application's logic continues to work unchanged.
 *
 * Safe and idempotent: every record is keyed on its stable number/code via
 * updateOrCreate(); child collections are replaced per parent. No tables are
 * truncated and no existing production rows are touched.
 */
class KhmerDemoDataSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'Password@12345';

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->call(RolePermissionSeeder::class);
            $this->call(KnowledgeBaseSeeder::class);
            $this->seedKnowledgeBaseKhmer();
            $this->call(ExpertRuleKhmerSeeder::class);

            $users = $this->seedUsers();
            $medicines = $this->seedMedicines();
            $this->seedInventory($medicines);
            $staff = $this->seedStaff($users);
            $this->seedSalaryHistories($staff, $users['admin'] ?? null);

            $patients = $this->seedPatients();
            $this->seedEncounters($patients, $users, $medicines);
        });
    }

    /**
     * Apply Khmer names (and categories) to the active knowledge base records.
     */
    private function seedKnowledgeBaseKhmer(): void
    {
        foreach ($this->symptomKhmer() as $code => $nameKm) {
            Symptom::where('code', $code)->update(['name_km' => $nameKm]);
        }

        foreach ($this->diseaseKhmer() as $code => $definition) {
            Disease::where('code', $code)->update([
                'name_km' => $definition['name'],
                'category' => $definition['category'],
            ]);
        }
    }

    /**
     * @return array<string, User>
     */
    private function seedUsers(): array
    {
        $definitions = [
            'doctor' => ['name' => 'លោកវេជ្ជបណ្ឌិត សុខ ចាន់ថា', 'email' => 'doctor@clinic.local', 'username' => 'doctor', 'role' => 'Doctor'],
            'doctor2' => ['name' => 'លោកវេជ្ជបណ្ឌិត លី ដារា', 'email' => 'doctor2@clinic.local', 'username' => 'doctor2', 'role' => 'Doctor'],
            'nurse' => ['name' => 'អ្នកគិលានុបដ្ឋាយិកា ចន់ សុភា', 'email' => 'nurse@clinic.local', 'username' => 'nurse', 'role' => 'Nurse'],
            'nurse2' => ['name' => 'អ្នកគិលានុបដ្ឋាយិកា ណុប នារី', 'email' => 'nurse2@clinic.local', 'username' => 'nurse2', 'role' => 'Nurse'],
            'receptionist' => ['name' => 'អ្នកទទួលភ្ញៀវ មាស បូរិន', 'email' => 'receptionist@clinic.local', 'username' => 'receptionist', 'role' => 'Receptionist'],
            'pharmacist' => ['name' => 'ឱសថការី ព្រាប មុនី', 'email' => 'pharmacist@clinic.local', 'username' => 'pharmacist', 'role' => 'Pharmacist'],
            'patient' => ['name' => 'អ្នកជំងឺ ទូច សុវណ្ណ', 'email' => 'patient@clinic.local', 'username' => 'patient', 'role' => 'Patient'],
        ];

        $users = [];

        // The administrator is never modified (its password stays as configured
        // by AdminUserSeeder); it is only resolved (or created) for audit links.
        $admin = User::where('email', 'admin@clinic.local')->first();

        if ($admin === null) {
            $admin = User::create([
                'name' => 'System Administrator',
                'username' => 'admin',
                'email' => 'admin@clinic.local',
                'password' => 'Admin@12345',
                'email_verified_at' => now(),
                'account_status' => User::STATUS_ACTIVE,
            ]);

            $admin->forceFill(['activated_at' => now()])->save();
        }

        $admin->syncRoles(['Admin']);
        $users['admin'] = $admin;

        foreach ($definitions as $key => $definition) {
            $user = User::updateOrCreate(
                ['email' => $definition['email']],
                [
                    'name' => $definition['name'],
                    'username' => $definition['username'],
                    'password' => self::DEMO_PASSWORD,
                    'email_verified_at' => now(),
                    'account_status' => User::STATUS_ACTIVE,
                ]
            );

            $user->forceFill(['activated_at' => $user->activated_at ?? now()])->save();
            $user->syncRoles([$definition['role']]);

            $users[$key] = $user;
        }

        return $users;
    }

    /**
     * @return array<string, Medicine>
     */
    private function seedMedicines(): array
    {
        $definitions = [
            ['code' => 'MED-0001', 'name' => 'ប៉ារ៉ាសេតាម៉ុល', 'generic' => 'Paracetamol', 'category' => 'បំបាត់ការឈឺចាប់ និងបញ្ចុះកម្តៅ', 'form' => 'គ្រាប់', 'strength' => '500 mg', 'unit' => 'គ្រាប់', 'manufacturer' => 'ក្រុមហ៊ុនឱសថកម្ពុជា', 'price' => 0.10, 'reorder' => 200, 'description' => 'ប្រើសម្រាប់បំបាត់ការឈឺចាប់ស្រាល និងបញ្ចុះកម្តៅ។'],
            ['code' => 'MED-0002', 'name' => 'អាម៉ុកស៊ីស៊ីលីន', 'generic' => 'Amoxicillin', 'category' => 'ថ្នាំអង់ទីប៊ីយ៉ូទិក', 'form' => 'គ្រាប់កាបស៊ូល', 'strength' => '500 mg', 'unit' => 'គ្រាប់', 'manufacturer' => 'ក្រុមហ៊ុនឱសថកម្ពុជា', 'price' => 0.25, 'reorder' => 150, 'description' => 'ថ្នាំអង់ទីប៊ីយ៉ូទិកសម្រាប់ការឆ្លងមេរោគដោយបាក់តេរី។'],
            ['code' => 'MED-0003', 'name' => 'អ៊ីប៊ូប្រូហ្វេន', 'generic' => 'Ibuprofen', 'category' => 'បំបាត់ការឈឺចាប់ និងរលាក', 'form' => 'គ្រាប់', 'strength' => '400 mg', 'unit' => 'គ្រាប់', 'manufacturer' => 'ក្រុមហ៊ុនឱសថកម្ពុជា', 'price' => 0.15, 'reorder' => 150, 'description' => 'បំបាត់ការឈឺចាប់ និងកាត់បន្ថយការរលាក។'],
            ['code' => 'MED-0004', 'name' => 'សារធាតុជំនួសទឹក ORS', 'generic' => 'Oral Rehydration Salts', 'category' => 'ជំនួយជំនួសទឹក និងអំបិលរាងកាយ', 'form' => 'ម្សៅលាយទឹក', 'strength' => '១កញ្ចប់/១លីត្រ', 'unit' => 'កញ្ចប់', 'manufacturer' => 'ក្រសួងសុខាភិបាល', 'price' => 0.50, 'reorder' => 100, 'description' => 'ជំនួយជំនួសទឹក និងអេឡិចត្រូលីតដែលបាត់បង់ពេលរាគ ឬក្អួត។'],
            ['code' => 'MED-0005', 'name' => 'សេតារីស៊ីន', 'generic' => 'Cetirizine', 'category' => 'ប្រឆាំងអាលែកស៊ី', 'form' => 'គ្រាប់', 'strength' => '10 mg', 'unit' => 'គ្រាប់', 'manufacturer' => 'ក្រុមហ៊ុនឱសថកម្ពុជា', 'price' => 0.10, 'reorder' => 120, 'description' => 'ប្រឆាំងអាលែកស៊ី និងបំបាត់រោគសញ្ញារលាកច្រមុះ។'],
            ['code' => 'MED-0006', 'name' => 'មេត្រូនីដាហ្សូល', 'generic' => 'Metronidazole', 'category' => 'ថ្នាំអង់ទីប៊ីយ៉ូទិក', 'form' => 'គ្រាប់', 'strength' => '250 mg', 'unit' => 'គ្រាប់', 'manufacturer' => 'ក្រុមហ៊ុនឱសថកម្ពុជា', 'price' => 0.20, 'reorder' => 100, 'description' => 'ព្យាបាលការឆ្លងមេរោគក្រពះពោះវៀន។'],
            ['code' => 'MED-0007', 'name' => 'អូមេប្រាហ្សូល', 'generic' => 'Omeprazole', 'category' => 'កាត់បន្ថយអាស៊ីតក្រពះ', 'form' => 'គ្រាប់កាបស៊ូល', 'strength' => '20 mg', 'unit' => 'គ្រាប់', 'manufacturer' => 'ក្រុមហ៊ុនឱសថកម្ពុជា', 'price' => 0.30, 'reorder' => 120, 'description' => 'កាត់បន្ថយអាស៊ីតក្រពះ និងព្យាបាលដំបៅក្រពះ។'],
            ['code' => 'MED-0008', 'name' => 'សាល់ប៊ូតាម៉ុល (ឧបករណ៍បាញ់)', 'generic' => 'Salbutamol Inhaler', 'category' => 'បើកផ្លូវដង្ហើម', 'form' => 'ឧបករណ៍បាញ់ផ្លូវដង្ហើម', 'strength' => '100 mcg/dose', 'unit' => 'ដប', 'manufacturer' => 'ក្រុមហ៊ុនឱសថអន្តរជាតិ', 'price' => 4.50, 'reorder' => 30, 'description' => 'បើកផ្លូវដង្ហើមសម្រាប់អ្នកជំងឺហឺត។'],
            ['code' => 'MED-0009', 'name' => 'អាមីឡូឌីពីន', 'generic' => 'Amlodipine', 'category' => 'បញ្ចុះសម្ពាធឈាម', 'form' => 'គ្រាប់', 'strength' => '5 mg', 'unit' => 'គ្រាប់', 'manufacturer' => 'ក្រុមហ៊ុនឱសថកម្ពុជា', 'price' => 0.20, 'reorder' => 150, 'description' => 'ព្យាបាលជំងឺលើសឈាម។'],
            ['code' => 'MED-0010', 'name' => 'មេតហ្វ័រមីន', 'generic' => 'Metformin', 'category' => 'ព្យាបាលទឹកនោមផ្អែម', 'form' => 'គ្រាប់', 'strength' => '500 mg', 'unit' => 'គ្រាប់', 'manufacturer' => 'ក្រុមហ៊ុនឱសថកម្ពុជា', 'price' => 0.15, 'reorder' => 200, 'description' => 'ព្យាបាលជំងឺទឹកនោមផ្អែមប្រភេទទី២។'],
            ['code' => 'MED-0011', 'name' => 'ដុកស៊ីស៊ីលីន', 'generic' => 'Doxycycline', 'category' => 'ថ្នាំអង់ទីប៊ីយ៉ូទិក', 'form' => 'គ្រាប់កាបស៊ូល', 'strength' => '100 mg', 'unit' => 'គ្រាប់', 'manufacturer' => 'ក្រុមហ៊ុនឱសថកម្ពុជា', 'price' => 0.30, 'reorder' => 100, 'description' => 'ថ្នាំអង់ទីប៊ីយ៉ូទិកសម្រាប់ការឆ្លងមេរោគផ្សេងៗ។'],
            ['code' => 'MED-0012', 'name' => 'វីតាមីន C', 'generic' => 'Ascorbic Acid', 'category' => 'វីតាមីនបំពេញបន្ថែម', 'form' => 'គ្រាប់', 'strength' => '500 mg', 'unit' => 'គ្រាប់', 'manufacturer' => 'ក្រុមហ៊ុនឱសថកម្ពុជា', 'price' => 0.05, 'reorder' => 300, 'description' => 'បំពេញបន្ថែមវីតាមីន C ដើម្បីបង្កើនភាពធន់។'],
        ];

        $medicines = [];

        foreach ($definitions as $definition) {
            $medicines[$definition['code']] = Medicine::updateOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'generic_name' => $definition['generic'],
                    'category' => $definition['category'],
                    'form' => $definition['form'],
                    'strength' => $definition['strength'],
                    'unit' => $definition['unit'],
                    'manufacturer' => $definition['manufacturer'],
                    'description' => $definition['description'],
                    'price' => $definition['price'],
                    'reorder_level' => $definition['reorder'],
                    'is_active' => true,
                ]
            );
        }

        return $medicines;
    }

    /**
     * @param  array<string, Medicine>  $medicines
     */
    private function seedInventory(array $medicines): void
    {
        $locations = ['ធ្នើ A1', 'ធ្នើ A2', 'ធ្នើ B1', 'ទូទឹកកក', 'ឃ្លាំងឱសថ'];

        $batchIndex = 1;

        foreach ($medicines as $code => $medicine) {
            $batchNumber = sprintf('BATCH-%04d', $batchIndex);

            $item = InventoryItem::updateOrCreate(
                ['medicine_id' => $medicine->id, 'batch_number' => $batchNumber],
                [
                    'quantity' => 300 + ($batchIndex * 17) % 400,
                    'expiration_date' => now()->addMonths(10 + ($batchIndex % 12))->toDateString(),
                    'received_date' => now()->subDays(30 + ($batchIndex * 3))->toDateString(),
                    'location' => $locations[$batchIndex % count($locations)],
                ]
            );

            StockMovement::updateOrCreate(
                ['reference' => $batchNumber, 'medicine_id' => $medicine->id],
                [
                    'inventory_item_id' => $item->id,
                    'user_id' => null,
                    'type' => 'in',
                    'quantity' => $item->quantity,
                    'reason' => 'ទទួលថ្នាំចូលឃ្លាំង',
                ]
            );

            $batchIndex++;
        }
    }

    /**
     * @param  array<string, User>  $users
     * @return array<string, Staff>
     */
    private function seedStaff(array $users): array
    {
        $definitions = [
            ['key' => 'doctor', 'number' => 'STF-0001', 'first' => 'សុខ', 'last' => 'ចាន់ថា', 'gender' => 'male', 'dob' => '1980-04-18', 'job' => 'វេជ្ជបណ្ឌិត', 'dept' => 'នាយកដ្ឋានព្យាបាលទូទៅ', 'type' => 'full_time', 'status' => 'employed', 'hire' => '2018-02-01', 'schedule' => 'ចន្ទ-សុក្រ 08:00-17:00', 'education' => 'វេជ្ជបណ្ឌិត (សាកលវិទ្យាល័យវិទ្យាសាស្ត្រសុខាភិបាល)', 'qualifications' => 'ជំនាញជំងឺទូទៅ និងព្យាបាលជំងឺផ្លូវដង្ហើម', 'license' => 'MD-KH-10233', 'expiry' => '2027-12-31', 'salary' => 2500.00, 'frequency' => 'monthly', 'allowances' => 200.00, 'supervisor' => null],
            ['key' => 'doctor2', 'number' => 'STF-0002', 'first' => 'លី', 'last' => 'ដារា', 'gender' => 'male', 'dob' => '1986-08-30', 'job' => 'វេជ្ជបណ្ឌិត', 'dept' => 'នាយកដ្ឋានព្យាបាលទូទៅ', 'type' => 'full_time', 'status' => 'employed', 'hire' => '2019-06-15', 'schedule' => 'ចន្ទ-សុក្រ 13:00-21:00', 'education' => 'វេជ្ជបណ្ឌិត (សាកលវិទ្យាល័យវិទ្យាសាស្ត្រសុខាភិបាល)', 'qualifications' => 'ជំនាញជំងឺកុមារ', 'license' => 'MD-KH-10588', 'expiry' => '2026-11-30', 'salary' => 2300.00, 'frequency' => 'monthly', 'allowances' => 180.00, 'supervisor' => 'STF-0001'],
            ['key' => 'nurse', 'number' => 'STF-0003', 'first' => 'ចន់', 'last' => 'សុភា', 'gender' => 'female', 'dob' => '1992-07-21', 'job' => 'គិលានុបដ្ឋាយិកា', 'dept' => 'ថែទាំអ្នកជំងឺ', 'type' => 'full_time', 'status' => 'employed', 'hire' => '2020-03-10', 'schedule' => 'ចន្ទ-សុក្រ 08:00-17:00', 'education' => 'សញ្ញាបត្រគិលានុបដ្ឋាយិកា', 'qualifications' => 'ការថែទាំមូលដ្ឋាន និងវាស់ស្ទង់សញ្ញាជីវិត', 'license' => 'RN-KH-20455', 'expiry' => '2027-03-31', 'salary' => 900.00, 'frequency' => 'monthly', 'allowances' => 80.00, 'supervisor' => 'STF-0001'],
            ['key' => 'nurse2', 'number' => 'STF-0004', 'first' => 'ណុប', 'last' => 'នារី', 'gender' => 'female', 'dob' => '1995-11-05', 'job' => 'គិលានុបដ្ឋាយិកា', 'dept' => 'ថែទាំអ្នកជំងឺ', 'type' => 'part_time', 'status' => 'employed', 'hire' => '2022-09-01', 'schedule' => 'អង្គារ-សៅរ៍ 08:00-12:00', 'education' => 'សញ្ញាបត្រគិលានុបដ្ឋាយិកា', 'qualifications' => 'ចាក់ថ្នាំ និងថែទាំបឋម', 'license' => 'RN-KH-20771', 'expiry' => '2026-08-31', 'salary' => 600.00, 'frequency' => 'monthly', 'allowances' => 50.00, 'supervisor' => 'STF-0003'],
            ['key' => 'receptionist', 'number' => 'STF-0005', 'first' => 'មាស', 'last' => 'បូរិន', 'gender' => 'female', 'dob' => '1990-05-08', 'job' => 'អ្នកទទួលភ្ញៀវ', 'dept' => 'រដ្ឋបាល និងទទួលភ្ញៀវ', 'type' => 'full_time', 'status' => 'employed', 'hire' => '2021-01-20', 'schedule' => 'ចន្ទ-សុក្រ 08:00-17:00', 'education' => 'បរិញ្ញាបត្ររដ្ឋបាល', 'qualifications' => 'សេវាកម្មអតិថិជន និងការគ្រប់គ្រងការណាត់ជួប', 'license' => null, 'expiry' => null, 'salary' => 700.00, 'frequency' => 'monthly', 'allowances' => 60.00, 'supervisor' => null],
            ['key' => 'pharmacist', 'number' => 'STF-0006', 'first' => 'ព្រាប', 'last' => 'មុនី', 'gender' => 'female', 'dob' => '1988-12-25', 'job' => 'ឱសថការី', 'dept' => 'ឱសថស្ថាន', 'type' => 'full_time', 'status' => 'employed', 'hire' => '2019-10-05', 'schedule' => 'ចន្ទ-សុក្រ 08:00-17:00', 'education' => 'បរិញ្ញាបត្រឱសថសាស្ត្រ', 'qualifications' => 'ចែកថ្នាំ និងគ្រប់គ្រងស្តុកឱសថ', 'license' => 'PH-KH-30812', 'expiry' => '2027-06-30', 'salary' => 950.00, 'frequency' => 'monthly', 'allowances' => 90.00, 'supervisor' => null],
            ['key' => null, 'number' => 'STF-0007', 'first' => 'ហេង', 'last' => 'វណ្ណា', 'gender' => 'male', 'dob' => '1998-01-15', 'job' => 'អ្នកសម្អាត', 'dept' => 'សេវាកម្មទូទៅ', 'type' => 'full_time', 'status' => 'employed', 'hire' => '2023-02-12', 'schedule' => 'ចន្ទ-សុក្រ 07:00-16:00', 'education' => 'បឋមសិក្សា', 'qualifications' => 'អនាម័យ និងសម្អាតអគារ', 'license' => null, 'expiry' => null, 'salary' => 350.00, 'frequency' => 'monthly', 'allowances' => 30.00, 'supervisor' => 'STF-0005'],
            ['key' => null, 'number' => 'STF-0008', 'first' => 'ទូច', 'last' => 'សុវណ្ណ', 'gender' => 'male', 'dob' => '1994-06-11', 'job' => 'អ្នកយាម', 'dept' => 'សុវត្ថិភាព', 'type' => 'contract', 'status' => 'employed', 'hire' => '2022-05-01', 'schedule' => 'រាល់ថ្ងៃ 18:00-06:00', 'education' => 'មធ្យមសិក្សា', 'qualifications' => 'សុវត្ថិភាព និងការឃ្លាំមើល', 'license' => null, 'expiry' => null, 'salary' => 400.00, 'frequency' => 'monthly', 'allowances' => 40.00, 'supervisor' => 'STF-0005'],
        ];

        $staff = [];
        $byNumber = [];

        foreach ($definitions as $definition) {
            $number = $definition['number'];

            $record = Staff::updateOrCreate(
                ['staff_number' => $number],
                [
                    'user_id' => $definition['key'] !== null ? ($users[$definition['key']]->id ?? null) : null,
                    'first_name' => $definition['first'],
                    'last_name' => $definition['last'],
                    'gender' => $definition['gender'],
                    'date_of_birth' => $definition['dob'],
                    'phone' => $this->khmerPhone($number),
                    'email' => $definition['key'] !== null ? ($users[$definition['key']]->email ?? null) : null,
                    'address' => $this->khmerAddress($number),
                    'emergency_contact_name' => $definition['first'].' '.$definition['last'],
                    'emergency_contact_phone' => $this->khmerPhone($number.'-emergency'),
                    'job_title' => $definition['job'],
                    'department' => $definition['dept'],
                    'employment_type' => $definition['type'],
                    'employment_status' => $definition['status'],
                    'hire_date' => $definition['hire'],
                    'work_schedule' => $definition['schedule'],
                    'education' => $definition['education'],
                    'qualifications' => $definition['qualifications'],
                    'license_number' => $definition['license'],
                    'license_expiry' => $definition['expiry'],
                    'salary_amount' => $definition['salary'],
                    'pay_frequency' => $definition['frequency'],
                    'allowances' => $definition['allowances'],
                ]
            );

            $byNumber[$number] = $record;
        }

        foreach ($definitions as $definition) {
            if ($definition['supervisor'] !== null && isset($byNumber[$definition['supervisor']])) {
                $byNumber[$definition['number']]->forceFill([
                    'supervisor_id' => $byNumber[$definition['supervisor']]->id,
                ])->save();
            }

            if ($definition['key'] !== null && isset($users[$definition['key']])) {
                $staff[$definition['key']] = $byNumber[$definition['number']];
            }
        }

        return $staff;
    }

    /**
     * @param  array<string, Staff>  $staff
     */
    private function seedSalaryHistories(array $staff, ?User $changedBy): void
    {
        foreach ($staff as $record) {
            StaffSalaryHistory::where('staff_id', $record->id)->delete();

            $current = (float) $record->salary_amount;

            StaffSalaryHistory::create([
                'staff_id' => $record->id,
                'changed_by' => $changedBy?->id,
                'amount' => round($current * 0.85, 2),
                'pay_frequency' => $record->pay_frequency,
                'allowances' => $record->allowances,
                'effective_from' => $record->hire_date?->toDateString(),
                'note' => 'ប្រាក់ខែចាប់ផ្តើម',
            ]);

            StaffSalaryHistory::create([
                'staff_id' => $record->id,
                'changed_by' => $changedBy?->id,
                'amount' => $current,
                'pay_frequency' => $record->pay_frequency,
                'allowances' => $record->allowances,
                'effective_from' => now()->subMonths(3)->toDateString(),
                'note' => 'ដំឡើងប្រាក់ខែប្រចាំឆ្នាំ',
            ]);
        }
    }

    /**
     * @return array<string, Patient>
     */
    private function seedPatients(): array
    {
        $definitions = [
            ['number' => 'PAT-2026-0001', 'first' => 'សុខ', 'last' => 'ចាន់ថា', 'gender' => 'male', 'dob' => '1985-03-12', 'phone' => '012345678', 'address' => 'ភ្នំពេញ ខណ្ឌចំការមន សង្កាត់ត្រពាំងជង', 'blood' => 'O+', 'allergies' => 'គ្មាន', 'emg' => 'សុខ សុភា', 'emg_phone' => '012888999'],
            ['number' => 'PAT-2026-0002', 'first' => 'ចន់', 'last' => 'សុភា', 'gender' => 'female', 'dob' => '1992-07-21', 'phone' => '092222333', 'address' => 'កណ្តាល ស្រុកព្រះស្តេច ឃុំកំពង់ស្វាយ', 'blood' => 'A+', 'allergies' => 'អាលែកស៊ីនឹងពេនីស៊ីលីន', 'emg' => 'ចន់ វិចិត្រ', 'emg_phone' => '017555666'],
            ['number' => 'PAT-2026-0003', 'first' => 'កែវ', 'last' => 'ស្រីនាថ', 'gender' => 'female', 'dob' => '1978-11-02', 'phone' => '011777888', 'address' => 'សៀមរាប ក្រុងសៀមរាប សង្កាត់ស្វាយដង្គំ', 'blood' => 'B+', 'allergies' => 'គ្មាន', 'emg' => 'កែវ ពិសិដ្ឋ', 'emg_phone' => '012999111'],
            ['number' => 'PAT-2026-0004', 'first' => 'ហេង', 'last' => 'វណ្ណា', 'gender' => 'male', 'dob' => '1996-01-15', 'phone' => '085444555', 'address' => 'បាត់ដំបង ក្រុងបាត់ដំបង សង្កាត់វត្តគរ', 'blood' => 'O-', 'allergies' => 'គ្មាន', 'emg' => 'ហេង ចន្ថា', 'emg_phone' => '096666777'],
            ['number' => 'PAT-2026-0005', 'first' => 'លី', 'last' => 'ដារា', 'gender' => 'male', 'dob' => '1988-09-30', 'phone' => '010333444', 'address' => 'កំពង់ចាម ក្រុងកំពង់ចាម សង្កាត់កំពង់ចាម', 'blood' => 'AB+', 'allergies' => 'គ្មាន', 'emg' => 'លី មុនី', 'emg_phone' => '093222111'],
            ['number' => 'PAT-2026-0006', 'first' => 'ព្រាប', 'last' => 'មុនី', 'gender' => 'female', 'dob' => '1990-05-08', 'phone' => '077888999', 'address' => 'ព្រៃវែង ស្រុកព្រៃវែង ឃុំបាភ្នំ', 'blood' => 'A-', 'allergies' => 'អាលែកស៊ីនឹងថ្នាំបំបាត់ការឈឺចាប់', 'emg' => 'ព្រាប សុភា', 'emg_phone' => '088555444'],
            ['number' => 'PAT-2026-0007', 'first' => 'ទូច', 'last' => 'សុវណ្ណ', 'gender' => 'male', 'dob' => '2001-02-19', 'phone' => '069111222', 'address' => 'តាកែវ ក្រុងដូនកែវ សង្កាត់រកាក្នុង', 'blood' => 'B-', 'allergies' => 'គ្មាន', 'emg' => 'ទូច នារី', 'emg_phone' => '070333444'],
            ['number' => 'PAT-2026-0008', 'first' => 'មាស', 'last' => 'បូរិន', 'gender' => 'female', 'dob' => '1983-12-25', 'phone' => '012666777', 'address' => 'កំពត ក្រុងកំពត សង្កាត់កំពត', 'blood' => 'O+', 'allergies' => 'គ្មាន', 'emg' => 'មាស សុវណ្ណ', 'emg_phone' => '015444333'],
            ['number' => 'PAT-2026-0009', 'first' => 'សំ', 'last' => 'រតនា', 'gender' => 'male', 'dob' => '1974-06-11', 'phone' => '081222333', 'address' => 'កំពង់ធំ ស្រុកបារាយណ៍ ឃុំបារាយណ៍', 'blood' => 'A+', 'allergies' => 'អាលែកស៊ីនឹងអាហារសមុទ្រ', 'emg' => 'សំ ចន្ថា', 'emg_phone' => '092111000'],
            ['number' => 'PAT-2026-0010', 'first' => 'យ៉ែម', 'last' => 'ចន្ថា', 'gender' => 'female', 'dob' => '1999-10-03', 'phone' => '095777888', 'address' => 'ព្រះសីហនុ ក្រុងព្រះសីហនុ សង្កាត់លេខ១', 'blood' => 'AB-', 'allergies' => 'គ្មាន', 'emg' => 'យ៉ែម សុភា', 'emg_phone' => '017999888'],
        ];

        $patients = [];

        foreach ($definitions as $index => $definition) {
            $patient = Patient::updateOrCreate(
                ['patient_number' => $definition['number']],
                [
                    'first_name' => $definition['first'],
                    'last_name' => $definition['last'],
                    'gender' => $definition['gender'],
                    'date_of_birth' => $definition['dob'],
                    'phone_number' => $definition['phone'],
                    'email' => strtolower($definition['number']).'@example.com',
                    'address' => $definition['address'],
                    'blood_type' => $definition['blood'],
                    'allergies' => $definition['allergies'],
                    'emergency_contact_name' => $definition['emg'],
                    'emergency_contact_phone' => $definition['emg_phone'],
                    'notes' => $definition['notes'] ?? null,
                ]
            );

            $patient->forceFill([
                'created_at' => now()->subDays($this->registrationDaysAgo($index)),
            ])->save();

            $patients[$definition['number']] = $patient;
        }

        return $patients;
    }

    /**
     * @param  array<string, Patient>  $patients
     * @param  array<string, User>  $users
     * @param  array<string, Medicine>  $medicines
     */
    private function seedEncounters(array $patients, array $users, array $medicines): void
    {
        $encounters = $this->encounterDefinitions();

        foreach ($encounters as $index => $encounter) {
            $sequence = $index + 1;
            $appointmentNumber = sprintf('APT-2026-%04d', $sequence);
            $appointmentDate = $encounter['days'] >= 0
                ? now()->subDays($encounter['days'])->toDateString()
                : now()->addDays(abs($encounter['days']))->toDateString();

            $patient = $patients[$encounter['patient']];
            $doctor = $users[$encounter['doctor']];

            $appointment = Appointment::updateOrCreate(
                ['appointment_number' => $appointmentNumber],
                [
                    'patient_id' => $patient->id,
                    'doctor_id' => $doctor->id,
                    'appointment_date' => $appointmentDate,
                    'appointment_time' => $encounter['time'],
                    'reason' => $encounter['reason'],
                    'status' => $encounter['status'],
                    'note' => $encounter['note'] ?? null,
                ]
            );

            if ($encounter['status'] !== 'completed') {
                continue;
            }

            $drugs = array_values(array_filter(
                array_map(fn ($code) => $medicines[$code] ?? null, $encounter['meds']),
            ));

            $this->seedCompletedEncounter(
                sequence: $sequence,
                encounter: $encounter,
                appointment: $appointment,
                patient: $patient,
                doctor: $doctor,
                nurse: $users[$encounter['nurse']],
                pharmacist: $users['pharmacist'],
                receptionist: $users['receptionist'],
                medicines: $drugs,
                appointmentDate: $appointmentDate,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $encounter
     * @param  array<int, Medicine>  $medicines
     */
    private function seedCompletedEncounter(
        int $sequence,
        array $encounter,
        Appointment $appointment,
        Patient $patient,
        User $doctor,
        User $nurse,
        User $pharmacist,
        User $receptionist,
        array $medicines,
        string $appointmentDate,
    ): void {
        $text = $this->clinicalText($encounter['disease']);
        $diseaseName = $this->diseaseKhmer()[$encounter['disease']]['name'] ?? $text['assessment'];

        VitalSign::updateOrCreate(
            ['appointment_id' => $appointment->id],
            [
                'patient_id' => $patient->id,
                'nurse_id' => $nurse->id,
                'temperature' => $encounter['temperature'],
                'blood_pressure_systolic' => $encounter['systolic'],
                'blood_pressure_diastolic' => $encounter['diastolic'],
                'heart_rate' => $encounter['heart_rate'],
                'respiratory_rate' => $encounter['respiratory_rate'],
                'oxygen_saturation' => $encounter['oxygen'],
                'weight' => $encounter['weight'],
                'height' => $encounter['height'],
                'symptoms' => $text['symptoms'],
                'notes' => $text['vitals_note'],
            ]
        );

        $consultation = Consultation::updateOrCreate(
            ['appointment_id' => $appointment->id],
            [
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'chief_complaint' => $text['complaint'],
                'symptoms' => $text['symptoms'],
                'clinical_notes' => $text['clinical_notes'],
                'examination' => $text['examination'],
                'assessment' => $text['assessment'],
                'plan' => $text['plan'],
            ]
        );

        Diagnosis::where('consultation_id', $consultation->id)->delete();

        Diagnosis::create([
            'consultation_id' => $consultation->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'diagnosis_name' => $diseaseName,
            'description' => $text['assessment'],
            'severity' => $encounter['severity'],
            'is_confirmed' => true,
            'notes' => $text['plan'],
        ]);

        MedicalRecord::updateOrCreate(
            ['consultation_id' => $consultation->id],
            [
                'patient_id' => $patient->id,
                'appointment_id' => $appointment->id,
                'doctor_id' => $doctor->id,
                'medical_history' => $patient->allergies !== 'គ្មាន'
                    ? 'អ្នកជំងឺមានប្រវត្តិអាលែកស៊ី៖ '.$patient->allergies
                    : 'គ្មានប្រវត្តិជំងឺរ៉ាំរ៉ៃ',
                'allergies' => $patient->allergies,
                'treatment_plan' => $text['plan'],
                'notes' => $text['notes'],
            ]
        );

        if ($medicines !== []) {
            $prescription = Prescription::updateOrCreate(
                ['prescription_number' => sprintf('RX-2026-%04d', $sequence)],
                [
                    'patient_id' => $patient->id,
                    'doctor_id' => $doctor->id,
                    'consultation_id' => $consultation->id,
                    'status' => $encounter['dispensed'] ? 'dispensed' : 'pending',
                    'notes' => $text['prescription_note'],
                    'issued_at' => $appointmentDate.' 09:00:00',
                    'dispensed_at' => $encounter['dispensed'] ? $appointmentDate.' 10:30:00' : null,
                    'dispensed_by' => $encounter['dispensed'] ? $pharmacist->id : null,
                ]
            );

            PrescriptionItem::where('prescription_id', $prescription->id)->delete();

            foreach ($medicines as $medicine) {
                $detail = $this->dosageFor($medicine->code);

                PrescriptionItem::create([
                    'prescription_id' => $prescription->id,
                    'medicine_id' => $medicine->id,
                    'dosage' => $detail['dosage'],
                    'frequency' => $detail['frequency'],
                    'duration' => $detail['duration'],
                    'quantity' => $detail['quantity'],
                    'instructions' => $detail['instructions'],
                ]);

                if ($encounter['dispensed']) {
                    StockMovement::updateOrCreate(
                        ['reference' => $prescription->prescription_number, 'medicine_id' => $medicine->id],
                        [
                            'inventory_item_id' => $medicine->inventoryItems()->first()?->id,
                            'user_id' => $pharmacist->id,
                            'type' => 'out',
                            'quantity' => $detail['quantity'],
                            'reason' => 'ចែកថ្នាំតាមវេជ្ជបញ្ជា '.$prescription->prescription_number,
                        ]
                    );
                }
            }
        }

        $this->seedInvoice(
            sequence: $sequence,
            patient: $patient,
            consultation: $consultation,
            receptionist: $receptionist,
            medicines: $medicines,
            paidFraction: $encounter['paid'],
            appointmentDate: $appointmentDate,
        );
    }

    /**
     * @param  array<int, Medicine>  $medicines
     */
    private function seedInvoice(
        int $sequence,
        Patient $patient,
        Consultation $consultation,
        User $receptionist,
        array $medicines,
        float $paidFraction,
        string $appointmentDate,
    ): void {
        $invoiceNumber = sprintf('INV-2026-%04d', $sequence);

        $lineItems = [
            ['description' => 'តម្លៃពិគ្រោះជំងឺជាមួយវេជ្ជបណ្ឌិត', 'quantity' => 1, 'unit_price' => 15.00],
        ];

        foreach ($medicines as $medicine) {
            $detail = $this->dosageFor($medicine->code);

            $lineItems[] = [
                'description' => $medicine->name.' '.$medicine->strength,
                'quantity' => $detail['quantity'],
                'unit_price' => (float) $medicine->price,
            ];
        }

        $subtotal = 0.0;

        foreach ($lineItems as $item) {
            $subtotal += $item['quantity'] * $item['unit_price'];
        }

        $subtotal = round($subtotal, 2);
        $total = $subtotal;
        $amountPaid = round($total * $paidFraction, 2);
        $balance = round($total - $amountPaid, 2);

        $status = match (true) {
            $amountPaid <= 0 && $total > 0 => 'unpaid',
            $amountPaid >= $total => 'paid',
            default => 'partially_paid',
        };

        $invoice = Invoice::updateOrCreate(
            ['invoice_number' => $invoiceNumber],
            [
                'patient_id' => $patient->id,
                'consultation_id' => $consultation->id,
                'status' => $status,
                'subtotal' => $subtotal,
                'discount' => 0,
                'tax' => 0,
                'total' => $total,
                'amount_paid' => $amountPaid,
                'balance' => $balance,
                'issued_at' => $appointmentDate,
                'due_date' => $appointmentDate,
                'notes' => 'វិក្កយបត្រសម្រាប់ការពិគ្រោះ និងឱសថ',
                'created_by' => $receptionist->id,
            ]
        );

        InvoiceItem::where('invoice_id', $invoice->id)->delete();

        foreach ($lineItems as $item) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'amount' => round($item['quantity'] * $item['unit_price'], 2),
            ]);
        }

        Payment::where('invoice_id', $invoice->id)->delete();

        if ($amountPaid > 0) {
            Payment::create([
                'invoice_id' => $invoice->id,
                'amount' => $amountPaid,
                'method' => 'cash',
                'reference' => 'PAY-2026-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
                'paid_at' => $appointmentDate.' 11:00:00',
                'received_by' => $receptionist->id,
                'notes' => $status === 'paid' ? 'បង់ប្រាក់ពេញ' : 'បង់ប្រាក់មួយផ្នែក',
            ]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function encounterDefinitions(): array
    {
        return [
            ['patient' => 'PAT-2026-0001', 'doctor' => 'doctor', 'nurse' => 'nurse', 'days' => 150, 'time' => '08:30', 'disease' => 'DIS-COMMON-COLD', 'severity' => 'mild', 'meds' => ['MED-0001', 'MED-0005'], 'paid' => 1.0, 'dispensed' => true, 'status' => 'completed', 'reason' => 'ក្អក ហៀរសំបោរ និងឈឺបំពង់ក', 'temperature' => 37.4, 'systolic' => 118, 'diastolic' => 76, 'heart_rate' => 82, 'respiratory_rate' => 18, 'oxygen' => 98.5, 'weight' => 62.5, 'height' => 168.0],
            ['patient' => 'PAT-2026-0002', 'doctor' => 'doctor', 'nurse' => 'nurse2', 'days' => 140, 'time' => '09:15', 'disease' => 'DIS-INFLUENZA', 'severity' => 'moderate', 'meds' => ['MED-0001', 'MED-0003'], 'paid' => 1.0, 'dispensed' => true, 'status' => 'completed', 'reason' => 'គ្រុនក្តៅខ្លាំង និងឈឺខ្លួនប្រាណ', 'temperature' => 38.6, 'systolic' => 110, 'diastolic' => 70, 'heart_rate' => 96, 'respiratory_rate' => 20, 'oxygen' => 97.5, 'weight' => 52.0, 'height' => 158.0],
            ['patient' => 'PAT-2026-0003', 'doctor' => 'doctor2', 'nurse' => 'nurse', 'days' => 120, 'time' => '10:00', 'disease' => 'DIS-STREP-PHARYNGITIS', 'severity' => 'moderate', 'meds' => ['MED-0002', 'MED-0001'], 'paid' => 0.5, 'dispensed' => true, 'status' => 'completed', 'reason' => 'ឈឺបំពង់កខ្លាំង និងលេបពិបាក', 'temperature' => 38.2, 'systolic' => 124, 'diastolic' => 80, 'heart_rate' => 90, 'respiratory_rate' => 19, 'oxygen' => 98.0, 'weight' => 58.0, 'height' => 160.0],
            ['patient' => 'PAT-2026-0004', 'doctor' => 'doctor', 'nurse' => 'nurse', 'days' => 95, 'time' => '14:30', 'disease' => 'DIS-GASTROENTERITIS', 'severity' => 'moderate', 'meds' => ['MED-0004', 'MED-0006'], 'paid' => 1.0, 'dispensed' => true, 'status' => 'completed', 'reason' => 'រាគ និងក្អួតច្រើនដង', 'temperature' => 37.9, 'systolic' => 108, 'diastolic' => 68, 'heart_rate' => 98, 'respiratory_rate' => 20, 'oxygen' => 98.0, 'weight' => 65.0, 'height' => 172.0],
            ['patient' => 'PAT-2026-0005', 'doctor' => 'doctor2', 'nurse' => 'nurse2', 'days' => 75, 'time' => '08:45', 'disease' => 'DIS-GASTRITIS', 'severity' => 'moderate', 'meds' => ['MED-0007'], 'paid' => 0.0, 'dispensed' => false, 'status' => 'completed', 'reason' => 'ឈឺក្រពះ និងក្រហាយទ្រូង', 'temperature' => 36.8, 'systolic' => 120, 'diastolic' => 78, 'heart_rate' => 78, 'respiratory_rate' => 18, 'oxygen' => 99.0, 'weight' => 70.0, 'height' => 175.0],
            ['patient' => 'PAT-2026-0006', 'doctor' => 'doctor', 'nurse' => 'nurse', 'days' => 50, 'time' => '11:20', 'disease' => 'DIS-UTI', 'severity' => 'moderate', 'meds' => ['MED-0002', 'MED-0001'], 'paid' => 1.0, 'dispensed' => true, 'status' => 'completed', 'reason' => 'នោមចុករោល និងនោមញឹកញាប់', 'temperature' => 37.6, 'systolic' => 116, 'diastolic' => 74, 'heart_rate' => 84, 'respiratory_rate' => 18, 'oxygen' => 98.5, 'weight' => 50.0, 'height' => 156.0],
            ['patient' => 'PAT-2026-0007', 'doctor' => 'doctor2', 'nurse' => 'nurse2', 'days' => 28, 'time' => '09:00', 'disease' => 'DIS-DENGUE', 'severity' => 'severe', 'meds' => ['MED-0001', 'MED-0004'], 'paid' => 0.5, 'dispensed' => true, 'status' => 'completed', 'reason' => 'គ្រុនក្តៅខ្លាំង ឈឺសន្លាក់ និងមានកន្ទួល', 'temperature' => 39.2, 'systolic' => 106, 'diastolic' => 66, 'heart_rate' => 102, 'respiratory_rate' => 20, 'oxygen' => 97.0, 'weight' => 55.0, 'height' => 170.0],
            ['patient' => 'PAT-2026-0008', 'doctor' => 'doctor', 'nurse' => 'nurse', 'days' => 18, 'time' => '15:00', 'disease' => 'DIS-HYPERTENSION', 'severity' => 'moderate', 'meds' => ['MED-0009'], 'paid' => 1.0, 'dispensed' => true, 'status' => 'completed', 'reason' => 'ឈឺក្បាល និងវិលមុខ', 'temperature' => 36.9, 'systolic' => 152, 'diastolic' => 96, 'heart_rate' => 88, 'respiratory_rate' => 18, 'oxygen' => 98.0, 'weight' => 68.0, 'height' => 159.0],
            ['patient' => 'PAT-2026-0009', 'doctor' => 'doctor2', 'nurse' => 'nurse', 'days' => 12, 'time' => '08:15', 'disease' => 'DIS-DIABETES', 'severity' => 'moderate', 'meds' => ['MED-0010'], 'paid' => 0.0, 'dispensed' => false, 'status' => 'completed', 'reason' => 'ស្រេកទឹកខ្លាំង និងនោមញឹកញាប់', 'temperature' => 36.7, 'systolic' => 132, 'diastolic' => 84, 'heart_rate' => 80, 'respiratory_rate' => 18, 'oxygen' => 98.5, 'weight' => 74.0, 'height' => 167.0],
            ['patient' => 'PAT-2026-0010', 'doctor' => 'doctor', 'nurse' => 'nurse2', 'days' => 8, 'time' => '10:40', 'disease' => 'DIS-MALARIA', 'severity' => 'severe', 'meds' => ['MED-0001', 'MED-0004'], 'paid' => 1.0, 'dispensed' => true, 'status' => 'completed', 'reason' => 'គ្រុនក្តៅខ្លាំង ត្រជាក់ញាក់ និងបែកញើស', 'temperature' => 38.9, 'systolic' => 112, 'diastolic' => 72, 'heart_rate' => 100, 'respiratory_rate' => 20, 'oxygen' => 97.5, 'weight' => 48.0, 'height' => 162.0],
            ['patient' => 'PAT-2026-0001', 'doctor' => 'doctor2', 'nurse' => 'nurse', 'days' => 4, 'time' => '09:30', 'disease' => 'DIS-ASTHMA', 'severity' => 'severe', 'meds' => ['MED-0008'], 'paid' => 0.5, 'dispensed' => true, 'status' => 'completed', 'reason' => 'ដកដង្ហើមខ្លី និងមានសំឡេងក្រហូង', 'temperature' => 37.1, 'systolic' => 122, 'diastolic' => 78, 'heart_rate' => 95, 'respiratory_rate' => 24, 'oxygen' => 95.0, 'weight' => 62.0, 'height' => 168.0],
            ['patient' => 'PAT-2026-0002', 'doctor' => 'doctor', 'nurse' => 'nurse2', 'days' => 2, 'time' => '13:45', 'disease' => 'DIS-MIGRAINE', 'severity' => 'moderate', 'meds' => ['MED-0001', 'MED-0003'], 'paid' => 0.0, 'dispensed' => false, 'status' => 'completed', 'reason' => 'ឈឺក្បាលចុចៗ និងខ្លាចពន្លឺ', 'temperature' => 36.8, 'systolic' => 114, 'diastolic' => 72, 'heart_rate' => 80, 'respiratory_rate' => 18, 'oxygen' => 99.0, 'weight' => 52.0, 'height' => 158.0],
            ['patient' => 'PAT-2026-0003', 'doctor' => 'doctor', 'nurse' => 'nurse', 'days' => -2, 'time' => '08:00', 'disease' => null, 'severity' => null, 'meds' => [], 'paid' => 0.0, 'dispensed' => false, 'status' => 'scheduled', 'reason' => 'ពិនិត្យសម្ពាធឈាមតាមកាលវិភាគ'],
            ['patient' => 'PAT-2026-0005', 'doctor' => 'doctor2', 'nurse' => 'nurse2', 'days' => -5, 'time' => '14:00', 'disease' => null, 'severity' => null, 'meds' => [], 'paid' => 0.0, 'dispensed' => false, 'status' => 'confirmed', 'reason' => 'ពិនិត្យក្រពះបន្ត'],
            ['patient' => 'PAT-2026-0007', 'doctor' => 'doctor', 'nurse' => 'nurse2', 'days' => -9, 'time' => '09:30', 'disease' => null, 'severity' => null, 'meds' => [], 'paid' => 0.0, 'dispensed' => false, 'status' => 'scheduled', 'reason' => 'តាមដានសុខភាពក្រោយគ្រុនឈាម'],
            ['patient' => 'PAT-2026-0009', 'doctor' => 'doctor2', 'nurse' => 'nurse', 'days' => -14, 'time' => '10:15', 'disease' => null, 'severity' => null, 'meds' => [], 'paid' => 0.0, 'dispensed' => false, 'status' => 'scheduled', 'reason' => 'ពិនិត្យជាតិស្ករក្នុងឈាម'],
            ['patient' => 'PAT-2026-0004', 'doctor' => 'doctor', 'nurse' => 'nurse', 'days' => 30, 'time' => '11:00', 'disease' => null, 'severity' => null, 'meds' => [], 'paid' => 0.0, 'dispensed' => false, 'status' => 'cancelled', 'reason' => 'ផ្លាស់ប្តូរកាលវិភាគដោយអ្នកជំងឺ'],
            ['patient' => 'PAT-2026-0006', 'doctor' => 'doctor2', 'nurse' => 'nurse2', 'days' => 20, 'time' => '09:45', 'disease' => null, 'severity' => null, 'meds' => [], 'paid' => 0.0, 'dispensed' => false, 'status' => 'no_show', 'reason' => 'អ្នកជំងឺមិនបានមកតាមការណាត់'],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function clinicalText(string $diseaseCode): array
    {
        $texts = [
            'DIS-COMMON-COLD' => [
                'complaint' => 'ឈឺបំពង់ក ហៀរសំបោរ និងក្អកស្ងួតពីរថ្ងៃមុន',
                'symptoms' => 'ហៀរសំបោរ ស្ទះច្រមុះ កណ្តាស់ និងឈឺបំពង់ក',
                'clinical_notes' => 'អ្នកជំងឺនិយាយថាចាប់ផ្តើមមានរោគសញ្ញាក្រោយជួបភ្លៀង។',
                'examination' => 'បំពង់កក្រហមស្រាល គ្មានចំណុចខ្ទឹម សួតច្បាស់ធម្មតា',
                'assessment' => 'រោគសញ្ញាសមស្របនឹងជំងឺផ្តាសាយធម្មតាដែលបង្កដោយវីរុស',
                'plan' => 'សម្រាកឲ្យបានគ្រប់គ្រាន់ ផឹកទឹកច្រើន លេបថ្នាំបំបាត់រោគសញ្ញា',
                'vitals_note' => 'គ្រុនក្តៅស្រាល',
                'prescription_note' => 'លេបថ្នាំតាមការណែនាំ ប្រសិនបើមិនធូរស្រាលក្នុង ៣ ថ្ងៃត្រូវត្រឡប់មកវិញ',
                'notes' => 'ណែនាំឲ្យលាងដៃញឹកញាប់ និងជៀសវាងការប៉ះពាល់អ្នកដទៃ',
            ],
            'DIS-INFLUENZA' => [
                'complaint' => 'គ្រុនក្តៅខ្លាំង ឈឺខ្លួនប្រាណ និងអស់កម្លាំង',
                'symptoms' => 'គ្រុនក្តៅខ្លាំង ត្រជាក់ញាក់ ឈឺសាច់ដុំ និងក្អកស្ងួត',
                'clinical_notes' => 'ចាប់ផ្តើមគ្រុនយ៉ាងឆាប់រហ័សក្នុងរយៈពេលមួយថ្ងៃ។',
                'examination' => 'អ្នកជំងឺមើលទៅអស់កម្លាំង បំពង់កក្រហម សួតច្បាស់ធម្មតា',
                'assessment' => 'រោគសញ្ញាសមស្របនឹងជំងឺផ្តាសាយតូចតាមរដូវកាល',
                'plan' => 'សម្រាកនៅផ្ទះ ផឹកទឹកច្រើន បញ្ចុះកម្តៅ និងតាមដានរោគសញ្ញា',
                'vitals_note' => 'គ្រុនក្តៅខ្លាំង',
                'prescription_note' => 'ប្រើថ្នាំបញ្ចុះកម្តៅតាមការចាំបាច់ និងសម្រាកឲ្យបានគ្រប់គ្រាន់',
                'notes' => 'ណែនាំត្រឡប់មកវិញប្រសិនបើដកដង្ហើមខ្លី ឬគ្រុនមិនចុះ',
            ],
            'DIS-STREP-PHARYNGITIS' => [
                'complaint' => 'ឈឺបំពង់កខ្លាំង និងឈឺពេលលេប',
                'symptoms' => 'ឈឺបំពង់ក គ្រុនក្តៅ និងឈឺក្បាល',
                'clinical_notes' => 'គ្មានរោគសញ្ញាក្អក និងហៀរសំបោរ។',
                'examination' => 'បំពង់កក្រហម និងមានចំណុចសនៅក្រពេញបំពង់ក',
                'assessment' => 'សង្ស័យរលាកបំពង់កដោយបាក់តេរី ត្រូវការថ្នាំអង់ទីប៊ីយ៉ូទិក',
                'plan' => 'ព្យាបាលដោយថ្នាំអង់ទីប៊ីយ៉ូទិក និងបំបាត់ការឈឺចាប់',
                'vitals_note' => 'មានគ្រុនក្តៅ',
                'prescription_note' => 'លេបថ្នាំអង់ទីប៊ីយ៉ូទិកឲ្យអស់កញ្ចប់ ទោះបីធូរស្រាលក៏ដោយ',
                'notes' => 'ប្រសិនបើគ្រុនបន្តលើស ៣ ថ្ងៃត្រូវត្រឡប់មកវិញ',
            ],
            'DIS-GASTROENTERITIS' => [
                'complaint' => 'រាគ និងក្អួតច្រើនដងចាប់ពីម្សិលមិញ',
                'symptoms' => 'រាគ ក្អួត ឈឺពោះ និងបាត់បង់ចំណង់អាហារ',
                'clinical_notes' => 'អ្នកជំងឺញ៉ាំអាហារក្នុងហាងរាល់ថ្ងៃ។',
                'examination' => 'ពោះទន់ មិនឈឺពេលចុច សញ្ញាខ្វះទឹកស្រាល',
                'assessment' => 'រលាកក្រពះពោះវៀនស្រួចស្រាវ ត្រូវការជំនួសទឹក',
                'plan' => 'ជំនួសទឹក និងអេឡិចត្រូលីត និងព្យាបាលតាមរោគសញ្ញា',
                'vitals_note' => 'មានសញ្ញាខ្វះទឹកស្រាល',
                'prescription_note' => 'លាយ ORS ជាមួយទឹកស្អាត និងផឹកបន្តិចម្តងៗ',
                'notes' => 'ណែនាំឲ្យញ៉ាំអាហារស្រាល និងត្រឡប់មកវិញប្រសិនបើរាគឈាម',
            ],
            'DIS-GASTRITIS' => [
                'complaint' => 'ឈឺក្រពះ និងក្រហាយទ្រូងបន្ទាប់ពីញ៉ាំអាហារ',
                'symptoms' => 'ឈឺក្រពះ ក្រហាយទ្រូង និងហើមពោះ',
                'clinical_notes' => 'រោគសញ្ញាកើតឡើងរាល់ពេលញ៉ាំអាហារហឹរ។',
                'examination' => 'ឈឺស្រាលនៅតំបន់ក្រពះ គ្មានសញ្ញាគ្រោះថ្នាក់',
                'assessment' => 'រលាកក្រពះទាក់ទងនឹងអាស៊ីត',
                'plan' => 'កាត់បន្ថយអាស៊ីតក្រពះ និងកែប្រែរបបអាហារ',
                'vitals_note' => 'សញ្ញាជីវិតធម្មតា',
                'prescription_note' => 'លេបថ្នាំមុនពេលញ៉ាំអាហារ ៣០ នាទី',
                'notes' => 'ជៀសវាងអាហារហឹរ ជាតិកាហ្វេអ៊ីន និងគ្រឿងស្រវឹង',
            ],
            'DIS-UTI' => [
                'complaint' => 'នោមចុករោល និងនោមញឹកញាប់',
                'symptoms' => 'នោមចុករោល នោមញឹកញាប់ និងឈឺចំហៀង',
                'clinical_notes' => 'អ្នកជំងឺមានអារម្មណ៍ថានោមមិនអស់។',
                'examination' => 'ឈឺស្រាលនៅតំបន់អាងត្រគាក គ្មានគ្រុនខ្លាំង',
                'assessment' => 'សង្ស័យឆ្លងមេរោគផ្លូវទឹកនោម',
                'plan' => 'ព្យាបាលដោយថ្នាំអង់ទីប៊ីយ៉ូទិក និងផឹកទឹកច្រើន',
                'vitals_note' => 'មានគ្រុនស្រាល',
                'prescription_note' => 'លេបថ្នាំឲ្យអស់កញ្ចប់ និងផឹកទឹកឲ្យបានច្រើន',
                'notes' => 'ត្រឡប់មកវិញប្រសិនបើឈឺចំហៀងខ្លាំង ឬគ្រុនខ្ពស់',
            ],
            'DIS-DENGUE' => [
                'complaint' => 'គ្រុនក្តៅខ្លាំង ឈឺសន្លាក់ និងមានកន្ទួល',
                'symptoms' => 'គ្រុនក្តៅខ្លាំង ឈឺសន្លាក់ ឈឺសាច់ដុំ និងឈឺខាងក្រោយគ្រាប់ភ្នែក',
                'clinical_notes' => 'អ្នកជំងឺរស់នៅតំបន់មានជំងឺគ្រុនឈាមរាតត្បាត។',
                'examination' => 'មានកន្ទួលស្បែក និងចំណុចឈាមក្រោមស្បែក',
                'assessment' => 'សង្ស័យជំងឺគ្រុនឈាម ត្រូវតាមដានចំនួនគ្រាប់ឈាម',
                'plan' => 'ជំនួសទឹក បញ្ចុះកម្តៅដោយប៉ារ៉ាសេតាម៉ុល និងតាមដានយ៉ាងជិត',
                'vitals_note' => 'គ្រុនក្តៅខ្លាំង',
                'prescription_note' => 'ហាមប្រើអ៊ីប៊ូប្រូហ្វេន ឬអាស្ពែរីន',
                'notes' => 'ណែនាំឲ្យត្រឡប់មកវិញជាបន្ទាន់ប្រសិនបើហូរឈាម',
            ],
            'DIS-HYPERTENSION' => [
                'complaint' => 'ឈឺក្បាល វិលមុខ និងភ្នែកព្រិល',
                'symptoms' => 'ឈឺក្បាល វិលមុខ និងភ្នែកព្រិល',
                'clinical_notes' => 'អ្នកជំងឺមិនបានលេបថ្នាំបញ្ចុះសម្ពាធឈាមទៀងទាត់។',
                'examination' => 'សម្ពាធឈាមខ្ពស់ គ្មានរោគសញ្ញាខាងសរសៃប្រសាទ',
                'assessment' => 'លើសឈាមមិនបានគ្រប់គ្រង',
                'plan' => 'លេបថ្នាំបញ្ចុះសម្ពាធឈាមទៀងទាត់ និងកាត់បន្ថយអំបិល',
                'vitals_note' => 'សម្ពាធឈាមខ្ពស់',
                'prescription_note' => 'លេបថ្នាំរៀងរាល់ថ្ងៃពេលព្រឹក ហាមឈប់ដោយខ្លួនឯង',
                'notes' => 'កាត់បន្ថយអំបិល និងតាមដានសម្ពាធឈាមជាប្រចាំ',
            ],
            'DIS-DIABETES' => [
                'complaint' => 'ស្រេកទឹកខ្លាំង នោមញឹកញាប់ និងស្រកទម្ងន់',
                'symptoms' => 'ស្រេកទឹក នោមញឹកញាប់ និងស្ពឹកជើង',
                'clinical_notes' => 'មានប្រវត្តិជំងឺទឹកនោមផ្អែមក្នុងគ្រួសារ។',
                'examination' => 'ជាតិស្ករក្នុងឈាមខ្ពស់ ស្បែកស្ងួត',
                'assessment' => 'ជំងឺទឹកនោមផ្អែមប្រភេទទី២ មិនបានគ្រប់គ្រងល្អ',
                'plan' => 'គ្រប់គ្រងរបបអាហារ និងលេបថ្នាំបញ្ចុះជាតិស្ករ',
                'vitals_note' => 'សញ្ញាជីវិតធម្មតា',
                'prescription_note' => 'លេបថ្នាំជាមួយអាហារ និងពិនិត្យជាតិស្ករជាប្រចាំ',
                'notes' => 'កាត់បន្ថយអាហារផ្អែម និងធ្វើលំហាត់ប្រាណទៀងទាត់',
            ],
            'DIS-MALARIA' => [
                'complaint' => 'គ្រុនក្តៅខ្លាំង ត្រជាក់ញាក់ និងបែកញើស',
                'symptoms' => 'គ្រុនក្តៅខ្លាំង ត្រជាក់ញាក់ បែកញើស និងឈឺក្បាល',
                'clinical_notes' => 'អ្នកជំងឺបានធ្វើដំណើរទៅតំបន់ព្រៃភ្នំ។',
                'examination' => 'គ្រុនក្តៅ និងស្លេកស្លាំងស្រាល',
                'assessment' => 'សង្ស័យជំងឺគ្រុនចាញ់ ត្រូវធ្វើតេស្តឈាមបញ្ជាក់',
                'plan' => 'ព្យាបាលដោយថ្នាំប្រឆាំងគ្រុនចាញ់ តាមការណែនាំរបស់ក្រសួងសុខាភិបាល',
                'vitals_note' => 'គ្រុនក្តៅខ្លាំង',
                'prescription_note' => 'ត្រូវលេបថ្នាំឲ្យអស់វគ្គ ទោះបីរោគសញ្ញាធូរស្រាល',
                'notes' => 'ណែនាំការពារខ្លួនពីមូសខាំ',
            ],
            'DIS-ASTHMA' => [
                'complaint' => 'ដកដង្ហើមខ្លី និងមានសំឡេងក្រហូងពេលដកដង្ហើម',
                'symptoms' => 'ដកដង្ហើមខ្លី ដកដង្ហើមមានសំឡេងក្រហូង និងតឹងទ្រូង',
                'clinical_notes' => 'កើតឡើងបន្ទាប់ពីប៉ះធូលី និងអាកាសធាតុត្រជាក់។',
                'examination' => 'ដកដង្ហើមលឿន មានសំឡេងក្រហូង និងប្រើសាច់ដុំជំនួយ',
                'assessment' => 'ជំងឺហឺតកំពុងស្រួចស្រាវ',
                'plan' => 'ប្រើឧបករណ៍បាញ់បើកផ្លូវដង្ហើម និងតាមដានការដកដង្ហើម',
                'vitals_note' => 'អុកស៊ីសែនក្នុងឈាមថយចុះស្រាល',
                'prescription_note' => 'ប្រើឧបករណ៍បាញ់ពេលដកដង្ហើមខ្លី ត្រូវទៅមន្ទីរពេទ្យបើមិនធូរស្រាល',
                'notes' => 'ជៀសវាងធូលី និងផ្សែងបារី',
            ],
            'DIS-MIGRAINE' => [
                'complaint' => 'ឈឺក្បាលចុចៗម្ខាង និងខ្លាចពន្លឺ',
                'symptoms' => 'ឈឺក្បាលចុចៗ ខ្លាចពន្លឺ និងចង់ក្អួត',
                'clinical_notes' => 'កើតឡើងម្តងម្កាល និងមានប្រវត្តិក្នុងគ្រួសារ។',
                'examination' => 'គ្មានសញ្ញាខាងសរសៃប្រសាទធ្ងន់ធ្ងរ',
                'assessment' => 'ជំងឺឈឺក្បាលប្រកេច',
                'plan' => 'សម្រាកក្នុងបន្ទប់ងងឹត និងបំបាត់ការឈឺចាប់',
                'vitals_note' => 'សញ្ញាជីវិតធម្មតា',
                'prescription_note' => 'លេបថ្នាំពេលចាប់ផ្តើមឈឺក្បាល',
                'notes' => 'កត់ត្រានូវអ្វីដែលបង្កការឈឺក្បាល ដើម្បីជៀសវាង',
            ],
        ];

        return $texts[$diseaseCode] ?? $texts['DIS-COMMON-COLD'];
    }

    /**
     * @return array<string, string>
     */
    private function dosageFor(string $code): array
    {
        $dosages = [
            'MED-0001' => ['dosage' => '១គ្រាប់ (500 mg)', 'frequency' => 'រៀងរាល់ ៦ ម៉ោង', 'duration' => '៥ ថ្ងៃ', 'quantity' => 20, 'instructions' => 'លេបពេលឈឺ ឬក្តៅខ្លួន'],
            'MED-0002' => ['dosage' => '១គ្រាប់កាបស៊ូល', 'frequency' => '៣ ដងក្នុងមួយថ្ងៃ', 'duration' => '៧ ថ្ងៃ', 'quantity' => 21, 'instructions' => 'លេបក្រោយអាហារ និងឲ្យអស់កញ្ចប់'],
            'MED-0003' => ['dosage' => '១គ្រាប់ (400 mg)', 'frequency' => '២ ដងក្នុងមួយថ្ងៃ', 'duration' => '៣ ថ្ងៃ', 'quantity' => 6, 'instructions' => 'លេបក្រោយអាហារ'],
            'MED-0004' => ['dosage' => '១កញ្ចប់លាយទឹក ១ លីត្រ', 'frequency' => 'ផឹកពេលរាគ', 'duration' => '៣ ថ្ងៃ', 'quantity' => 5, 'instructions' => 'ផឹកជំនួសទឹកដែលបាត់បង់'],
            'MED-0005' => ['dosage' => '១គ្រាប់ (10 mg)', 'frequency' => '១ ដងក្នុងមួយថ្ងៃ ពេលយប់', 'duration' => '៥ ថ្ងៃ', 'quantity' => 5, 'instructions' => 'អាចបណ្តាលឲ្យងងឹតងងុល'],
            'MED-0006' => ['dosage' => '១គ្រាប់ (250 mg)', 'frequency' => '៣ ដងក្នុងមួយថ្ងៃ', 'duration' => '៥ ថ្ងៃ', 'quantity' => 15, 'instructions' => 'លេបក្រោយអាហារ និងជៀសវាងគ្រឿងស្រវឹង'],
            'MED-0007' => ['dosage' => '១គ្រាប់កាបស៊ូល (20 mg)', 'frequency' => '១ ដងក្នុងមួយថ្ងៃ មុនអាហារ', 'duration' => '១៤ ថ្ងៃ', 'quantity' => 14, 'instructions' => 'លេបមុនញ៉ាំអាហារ ៣០ នាទី'],
            'MED-0008' => ['dosage' => 'បាញ់ ២ ដង', 'frequency' => '៤ ដងក្នុងមួយថ្ងៃ ពេលត្រូវការ', 'duration' => 'តាមការចាំបាច់', 'quantity' => 1, 'instructions' => 'ប្រើពេលដកដង្ហើមខ្លី'],
            'MED-0009' => ['dosage' => '១គ្រាប់ (5 mg)', 'frequency' => '១ ដងក្នុងមួយថ្ងៃ', 'duration' => '៣០ ថ្ងៃ', 'quantity' => 30, 'instructions' => 'លេបពេលព្រឹកជាប់លាប់'],
            'MED-0010' => ['dosage' => '១គ្រាប់ (500 mg)', 'frequency' => '២ ដងក្នុងមួយថ្ងៃ ជាមួយអាហារ', 'duration' => '៣០ ថ្ងៃ', 'quantity' => 60, 'instructions' => 'លេបជាមួយអាហារ'],
            'MED-0011' => ['dosage' => '១គ្រាប់កាបស៊ូល (100 mg)', 'frequency' => '២ ដងក្នុងមួយថ្ងៃ', 'duration' => '៧ ថ្ងៃ', 'quantity' => 14, 'instructions' => 'លេបជាមួយទឹកច្រើន'],
            'MED-0012' => ['dosage' => '១គ្រាប់ (500 mg)', 'frequency' => '១ ដងក្នុងមួយថ្ងៃ', 'duration' => '៣០ ថ្ងៃ', 'quantity' => 30, 'instructions' => 'បន្ថែមភាពធន់នៃរាងកាយ'],
        ];

        return $dosages[$code] ?? $dosages['MED-0001'];
    }

    /**
     * @return array<string, string>
     */
    private function symptomKhmer(): array
    {
        return [
            'SYM-FEVER' => 'គ្រុនក្តៅ',
            'SYM-HIGH-FEVER' => 'គ្រុនក្តៅខ្លាំង',
            'SYM-CHILLS' => 'ត្រជាក់ញាក់',
            'SYM-FATIGUE' => 'អស់កម្លាំង',
            'SYM-MALAISE' => 'មិនស្រួលខ្លួន',
            'SYM-BODY-ACHE' => 'ឈឺខ្លួនប្រាណ',
            'SYM-JOINT-PAIN' => 'ឈឺសន្លាក់',
            'SYM-MUSCLE-PAIN' => 'ឈឺសាច់ដុំ',
            'SYM-HEADACHE' => 'ឈឺក្បាល',
            'SYM-THROBBING-HEADACHE' => 'ឈឺក្បាលចុចៗ',
            'SYM-BAND-HEADACHE' => 'ឈឺក្បាលរុំជុំវិញ',
            'SYM-PHOTOPHOBIA' => 'ខ្លាចពន្លឺ',
            'SYM-NAUSEA' => 'ចង់ក្អួត',
            'SYM-VOMITING' => 'ក្អួត',
            'SYM-DIARRHEA' => 'រាគ',
            'SYM-CONSTIPATION' => 'ទល់លាមក',
            'SYM-ABDOMINAL-PAIN' => 'ឈឺពោះ',
            'SYM-EPIGASTRIC-PAIN' => 'ឈឺក្រពះ',
            'SYM-HEARTBURN' => 'ក្រហាយទ្រូង',
            'SYM-BLOATING' => 'ហើមពោះ',
            'SYM-BLOOD-IN-STOOL' => 'ឈាមក្នុងលាមក',
            'SYM-BLACK-STOOL' => 'លាមកខ្មៅ',
            'SYM-COUGH' => 'ក្អក',
            'SYM-DRY-COUGH' => 'ក្អកស្ងួត',
            'SYM-PRODUCTIVE-COUGH' => 'ក្អកមានស្លេស្ម',
            'SYM-COUGH-BLOOD' => 'ក្អកឈាម',
            'SYM-SORE-THROAT' => 'ឈឺបំពង់ក',
            'SYM-RUNNY-NOSE' => 'ហៀរសំបោរ',
            'SYM-NASAL-CONGESTION' => 'ស្ទះច្រមុះ',
            'SYM-SNEEZING' => 'កណ្តាស់',
            'SYM-ITCHY-EYES' => 'រមាស់ភ្នែក',
            'SYM-ITCHY-NOSE' => 'រមាស់ច្រមុះ',
            'SYM-SHORTNESS-OF-BREATH' => 'ដកដង្ហើមខ្លី',
            'SYM-WHEEZING' => 'ដកដង្ហើមមានសំឡេងក្រហូង',
            'SYM-CHEST-TIGHTNESS' => 'តឹងទ្រូង',
            'SYM-CHEST-PAIN-BREATHING' => 'ឈឺទ្រូងពេលដកដង្ហើម',
            'SYM-LOSS-OF-SMELL' => 'បាត់ក្លិន',
            'SYM-LOSS-OF-TASTE' => 'បាត់រសជាតិ',
            'SYM-HOARSENESS' => 'សំឡេងស្អក',
            'SYM-DIZZINESS' => 'វិលមុខ',
            'SYM-BLURRED-VISION' => 'ព្រិលភ្នែក',
            'SYM-EXCESSIVE-THIRST' => 'ស្រេកទឹកខ្លាំង',
            'SYM-FREQUENT-URINATION' => 'នោមញឹកញាប់',
            'SYM-BURNING-URINATION' => 'នោមចុករោល',
            'SYM-URGENCY' => 'ចង់នោមប្រញាប់',
            'SYM-FLANK-PAIN' => 'ឈឺចំហៀង',
            'SYM-BLOOD-IN-URINE' => 'ឈាមក្នុងទឹកនោម',
            'SYM-RASH' => 'កន្ទួលលើស្បែក',
            'SYM-RETROORBITAL-PAIN' => 'ឈឺខាងក្រោយគ្រាប់ភ្នែក',
            'SYM-BLEEDING-GUMS' => 'អញ្ចាញធ្មេញហូរឈាម',
            'SYM-NIGHT-SWEATS' => 'បែកញើសពេលយប់',
            'SYM-WEIGHT-LOSS' => 'ស្រកទម្ងន់',
            'SYM-WEIGHT-GAIN' => 'ឡើងទម្ងន់',
            'SYM-COLD-INTOLERANCE' => 'មិនអាចទ្រាំនឹងភាពត្រជាក់',
            'SYM-PALPITATIONS' => 'បេះដូងលោតញាប់',
            'SYM-SLOW-HEALING-WOUNDS' => 'របួសជាសះយឺត',
            'SYM-NUMBNESS-FEET' => 'ស្ពឹកជើង',
            'SYM-PALLOR' => 'ស្លេកស្លាំង',
            'SYM-LOSS-OF-APPETITE' => 'បាត់បង់ចំណង់អាហារ',
            'SYM-SWEATING' => 'បែកញើសច្រើន',
            'SYM-NECK-STIFFNESS' => 'ករឹង',
            'SYM-PELVIC-PAIN' => 'ឈឺអាងត្រគាក',
            'SYM-FACIAL-PAIN' => 'ឈឺមុខ',
        ];
    }

    /**
     * @return array<string, array{name: string, category: string}>
     */
    private function diseaseKhmer(): array
    {
        return [
            'DIS-COMMON-COLD' => ['name' => 'គ្រុនផ្តាសាយធម្មតា', 'category' => 'ជំងឺផ្លូវដង្ហើម'],
            'DIS-INFLUENZA' => ['name' => 'គ្រុនផ្តាសាយរដូវកាល', 'category' => 'ជំងឺផ្លូវដង្ហើម'],
            'DIS-COVID19' => ['name' => 'ជំងឺកូវីដ-១៩', 'category' => 'ជំងឺផ្លូវដង្ហើម'],
            'DIS-STREP-PHARYNGITIS' => ['name' => 'រលាកបំពង់កដោយបាក់តេរីស្ត្រីប', 'category' => 'ជំងឺផ្លូវដង្ហើម'],
            'DIS-ACUTE-BRONCHITIS' => ['name' => 'រលាកបំពង់ខ្យល់ស្រួចស្រាវ', 'category' => 'ជំងឺផ្លូវដង្ហើម'],
            'DIS-PNEUMONIA' => ['name' => 'រលាកសួត', 'category' => 'ជំងឺផ្លូវដង្ហើម'],
            'DIS-ALLERGIC-RHINITIS' => ['name' => 'រលាកច្រមុះដោយអាលែកស៊ី', 'category' => 'ជំងឺអាលែកស៊ី'],
            'DIS-ASTHMA' => ['name' => 'ជំងឺហឺត', 'category' => 'ជំងឺផ្លូវដង្ហើម'],
            'DIS-MIGRAINE' => ['name' => 'ជំងឺឈឺក្បាលប្រកេច', 'category' => 'ជំងឺប្រព័ន្ធសរសៃប្រសាទ'],
            'DIS-TENSION-HEADACHE' => ['name' => 'ឈឺក្បាលដោយសារភាពតានតឹង', 'category' => 'ជំងឺប្រព័ន្ធសរសៃប្រសាទ'],
            'DIS-GASTROENTERITIS' => ['name' => 'រលាកក្រពះពោះវៀនស្រួចស្រាវ', 'category' => 'ជំងឺប្រព័ន្ធរំលាយអាហារ'],
            'DIS-GASTRITIS' => ['name' => 'រលាកក្រពះ និងដំបៅក្រពះ', 'category' => 'ជំងឺប្រព័ន្ធរំលាយអាហារ'],
            'DIS-UTI' => ['name' => 'ការឆ្លងមេរោគផ្លូវទឹកនោម', 'category' => 'ជំងឺផ្លូវទឹកនោម'],
            'DIS-DENGUE' => ['name' => 'ជំងឺគ្រុនឈាម', 'category' => 'ជំងឺឆ្លង'],
            'DIS-TYPHOID' => ['name' => 'ជំងឺគ្រុនពោះវៀន', 'category' => 'ជំងឺឆ្លង'],
            'DIS-HYPERTENSION' => ['name' => 'ជំងឺលើសឈាម', 'category' => 'ជំងឺបេះដូង និងសរសៃឈាម'],
            'DIS-DIABETES' => ['name' => 'ជំងឺទឹកនោមផ្អែមប្រភេទទី២', 'category' => 'ជំងឺក្រពេញ'],
            'DIS-IRON-ANEMIA' => ['name' => 'ជំងឺស្លេកស្លាំងខ្វះជាតិដែក', 'category' => 'ជំងឺឈាម'],
            'DIS-HYPOTHYROIDISM' => ['name' => 'ជំងឺក្រពេញទីរ៉ូអ៊ីតសកម្មភាពថយចុះ', 'category' => 'ជំងឺក្រពេញ'],
            'DIS-MALARIA' => ['name' => 'ជំងឺគ្រុនចាញ់', 'category' => 'ជំងឺឆ្លង'],
        ];
    }

    private function registrationDaysAgo(int $index): int
    {
        $days = [1, 3, 6, 9, 12, 35, 70, 110, 160, 210];

        return $days[$index] ?? ($index * 20);
    }

    private function khmerPhone(string $seed): string
    {
        $number = (int) substr(md5($seed), 0, 8);

        return '0'.substr((string) (10000000 + ($number % 90000000)), 0, 8);
    }

    private function khmerAddress(string $seed): string
    {
        $addresses = [
            'ភ្នំពេញ ខណ្ឌដូនពេញ សង្កាត់ផ្សារថ្មី',
            'ភ្នំពេញ ខណ្ឌសែនសុខ សង្កាត់ភ្នំពេញថ្មី',
            'កណ្តាល ស្រុកតាខ្មៅ ឃុំតាក្តុល',
            'សៀមរាប ក្រុងសៀមរាប សង្កាត់ស្លក្រាម',
            'បាត់ដំបង ក្រុងបាត់ដំបង សង្កាត់រតនៈ',
        ];

        return $addresses[(int) (hexdec(substr(md5($seed), 0, 4)) % count($addresses))];
    }
}
