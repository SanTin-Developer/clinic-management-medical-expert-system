<?php

namespace Database\Seeders;

use App\Models\Staff;
use App\Models\StaffDocument;
use App\Models\StaffSalaryHistory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Production-like staff directory: exactly 40 employees across the six
 * departments used by RoyalCare Clinic.
 *
 * The first eight staff numbers (STF-0001 – STF-0008) already exist from
 * KhmerDemoDataSeeder and are updated in place so their linked user accounts
 * survive; STF-0009 – STF-0040 are added. Every record is keyed on its stable
 * staff number, so the seeder is idempotent and never duplicates rows.
 *
 * Also seeds:
 *  - salary history (three append-only entries per employee)
 *  - confidential staff documents backed by real placeholder PDFs
 *  - two accounts left in "pending_approval" so the approvals queue is populated
 */
class StaffDirectorySeeder extends Seeder
{
    private const DEMO_PASSWORD = 'Password@12345';

    private const DEPARTMENT_MEDICAL = 'Medical Department';

    private const DEPARTMENT_NURSING = 'Nursing Department';

    private const DEPARTMENT_PHARMACY = 'Pharmacy Department';

    private const DEPARTMENT_LABORATORY = 'Laboratory & Diagnostics';

    private const DEPARTMENT_ADMINISTRATION = 'Administration & Reception';

    private const DEPARTMENT_OPERATIONS = 'Operations & Support';

    public function run(): void
    {
        DB::transaction(function (): void {
            $staff = $this->seedStaff();
            $this->seedSalaryHistories($staff);
            $this->seedStaffDocuments($staff);
            $this->seedPendingApprovals($staff);
        });
    }

    /**
     * @return array<string, Staff>
     */
    private function seedStaff(): array
    {
        $byNumber = [];
        $byUserKey = [];

        foreach ($this->definitions() as $definition) {
            $number = $definition['number'];

            $linkedUser = $definition['user'] !== null
                ? User::where('username', $definition['user'])->first()
                : null;

            /** @var Staff $record */
            $record = Staff::updateOrCreate(
                ['staff_number' => $number],
                [
                    'user_id' => $linkedUser?->id,
                    'first_name' => $definition['first'],
                    'last_name' => $definition['last'],
                    'gender' => $definition['gender'],
                    'date_of_birth' => $definition['dob'],
                    'phone' => $this->phone($number),
                    'email' => $definition['email'] ?? $linkedUser?->email,
                    'address' => $this->address($number),
                    'emergency_contact_name' => $definition['emergency_name'],
                    'emergency_contact_phone' => $this->phone($number.'-emergency'),
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
                    'pay_frequency' => 'monthly',
                    'allowances' => $definition['allowances'],
                ]
            );

            if ($definition['created'] !== null) {
                $record->forceFill(['created_at' => $definition['created'].' 09:00:00'])->save();
            }

            // Keep the reverse link on the user account in sync with the profile.
            if ($definition['user'] !== null) {
                User::where('username', $definition['user'])
                    ->update(['staff_id' => $number]);
                $byUserKey[$definition['user']] = $record;
            }

            $byNumber[$number] = $record;
        }

        // Supervisors are resolved in a second pass so every row exists.
        foreach ($this->definitions() as $definition) {
            if ($definition['supervisor'] === null) {
                continue;
            }

            $supervisor = $byNumber[$definition['supervisor']] ?? null;

            if ($supervisor !== null) {
                $byNumber[$definition['number']]->forceFill([
                    'supervisor_id' => $supervisor->id,
                ])->save();
            }
        }

        return $byNumber;
    }

    /**
     * @param  array<string, Staff>  $staff
     */
    private function seedSalaryHistories(array $staff): void
    {
        $adminId = User::where('username', 'admin')->value('id');

        foreach ($staff as $record) {
            // Never replace compensation history that already exists — it may
            // be real data on a shared environment.
            if ($record->salaryHistories()->exists()) {
                continue;
            }

            $current = (float) $record->salary_amount;
            $hire = $record->hire_date?->toDateString() ?? now()->subYear()->toDateString();

            StaffSalaryHistory::create([
                'staff_id' => $record->id,
                'changed_by' => $adminId,
                'amount' => round($current * 0.80, 2),
                'pay_frequency' => $record->pay_frequency,
                'allowances' => $record->allowances,
                'effective_from' => $hire,
                'note' => 'Starting salary on appointment.',
            ]);

            StaffSalaryHistory::create([
                'staff_id' => $record->id,
                'changed_by' => $adminId,
                'amount' => round($current * 0.90, 2),
                'pay_frequency' => $record->pay_frequency,
                'allowances' => $record->allowances,
                'effective_from' => now()->subYear()->toDateString(),
                'note' => 'Annual cost-of-living adjustment.',
            ]);

            StaffSalaryHistory::create([
                'staff_id' => $record->id,
                'changed_by' => $adminId,
                'amount' => $current,
                'pay_frequency' => $record->pay_frequency,
                'allowances' => $record->allowances,
                'effective_from' => now()->subMonths(3)->toDateString(),
                'note' => 'Performance review adjustment.',
            ]);
        }
    }

    /**
     * @param  array<string, Staff>  $staff
     */
    private function seedStaffDocuments(array $staff): void
    {
        $adminId = User::where('username', 'admin')->value('id');

        foreach ($staff as $record) {
            // Never grow the document list on every re-seed.
            if ($record->documents()->exists()) {
                continue;
            }

            $documents = $this->documentsFor($record);

            foreach ($documents as $document) {
                $path = 'staff-documents/'.$record->id.'/'.Str::uuid()->toString().'.pdf';
                Storage::disk('local')->put($path, $this->makePdf($document['title']));

                StaffDocument::create([
                    'staff_id' => $record->id,
                    'uploaded_by' => $adminId,
                    'title' => $document['title'],
                    'category' => $document['category'],
                    'original_name' => $document['original_name'],
                    'path' => $path,
                    'mime_type' => 'application/pdf',
                    'size' => $this->pdfSize(),
                ]);
            }
        }
    }

    /**
     * @param  array<string, Staff>  $staff
     */
    private function seedPendingApprovals(array $staff): void
    {
        $adminId = User::where('username', 'admin')->value('id');

        $pending = [
            [
                'username' => 'dr.nimol',
                'name' => 'និមល សុខា',
                'email' => 'dr.nimol@clinic.local',
                'role' => 'Doctor',
                'staff_number' => 'STF-0014',
            ],
            [
                'username' => 'nurse.dalin',
                'name' => 'ដាលីន ចន្ថា',
                'email' => 'nurse.dalin@clinic.local',
                'role' => 'Nurse',
                'staff_number' => 'STF-0017',
            ],
        ];

        foreach ($pending as $definition) {
            $user = User::updateOrCreate(
                ['email' => $definition['email']],
                [
                    'name' => $definition['name'],
                    'username' => $definition['username'],
                    'password' => self::DEMO_PASSWORD,
                    'email_verified_at' => now(),
                    'activated_at' => now(),
                    'account_status' => User::STATUS_PENDING_APPROVAL,
                    'staff_id' => $definition['staff_number'],
                ]
            );

            $user->syncRoles([$definition['role']]);

            $staffRecord = $staff[$definition['staff_number']] ?? null;

            if ($staffRecord !== null) {
                $staffRecord->forceFill(['user_id' => $user->id])->save();
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function definitions(): array
    {
        return [
            // ------------------------------------------------------------------
            // 1. Medical Department (9)
            // ------------------------------------------------------------------
            [
                'number' => 'STF-0001', 'user' => 'doctor', 'first' => 'សុខ', 'last' => 'ចាន់ថា',
                'gender' => 'male', 'dob' => '1980-04-18', 'job' => 'Medical Director',
                'dept' => self::DEPARTMENT_MEDICAL, 'type' => 'full_time', 'status' => 'employed',
                'hire' => '2018-02-01', 'schedule' => 'Mon-Fri 08:00-17:00',
                'education' => 'Doctor of Medicine (University of Health Sciences)',
                'qualifications' => 'General practice, respiratory medicine, clinical leadership',
                'license' => 'MD-KH-10233', 'expiry' => '2027-12-31',
                'salary' => 3200.00, 'allowances' => 320.00, 'supervisor' => null,
                'emergency_name' => 'សុខ សុភា', 'created' => '2018-02-01',
            ],
            [
                'number' => 'STF-0002', 'user' => 'doctor2', 'first' => 'លី', 'last' => 'ដារា',
                'gender' => 'male', 'dob' => '1986-08-30', 'job' => 'General Practitioner',
                'dept' => self::DEPARTMENT_MEDICAL, 'type' => 'full_time', 'status' => 'employed',
                'hire' => '2019-06-15', 'schedule' => 'Mon-Fri 13:00-21:00',
                'education' => 'Doctor of Medicine (University of Health Sciences)',
                'qualifications' => 'Family medicine, paediatric primary care',
                'license' => 'MD-KH-10588', 'expiry' => '2026-11-30',
                'salary' => 2200.00, 'allowances' => 220.00, 'supervisor' => 'STF-0001',
                'emergency_name' => 'លី មុនី', 'created' => '2019-06-15',
            ],
            [
                'number' => 'STF-0009', 'user' => null, 'first' => 'សុខ', 'last' => 'សុខា',
                'gender' => 'male', 'dob' => '1990-02-14', 'job' => 'General Practitioner',
                'dept' => self::DEPARTMENT_MEDICAL, 'type' => 'full_time', 'status' => 'employed',
                'hire' => '2026-09-01', 'schedule' => 'Mon-Fri 08:00-17:00',
                'education' => 'Doctor of Medicine (University of Health Sciences)',
                'qualifications' => 'Primary care, chronic disease management',
                'license' => 'MD-KH-11240', 'expiry' => '2028-08-31',
                'salary' => 2200.00, 'allowances' => 220.00, 'supervisor' => 'STF-0001',
                'emergency_name' => 'សុខ នារី', 'created' => '2026-09-01',
            ],
            [
                'number' => 'STF-0010', 'user' => null, 'first' => 'វិចិត្រ', 'last' => 'ចាន់',
                'gender' => 'male', 'dob' => '1978-06-22', 'job' => 'Cardiologist',
                'dept' => self::DEPARTMENT_MEDICAL, 'type' => 'full_time', 'status' => 'employed',
                'hire' => '2021-03-15', 'schedule' => 'Mon-Fri 09:00-17:00',
                'education' => 'MD, Fellowship in Interventional Cardiology',
                'qualifications' => 'ECG, echocardiography, interventional cardiology',
                'license' => 'MD-KH-10914', 'expiry' => '2027-05-31',
                'salary' => 2800.00, 'allowances' => 280.00, 'supervisor' => 'STF-0001',
                'emergency_name' => 'វិចិត្រ សុភា', 'created' => '2021-03-15',
            ],
            [
                'number' => 'STF-0011', 'user' => null, 'first' => 'សឿន', 'last' => 'លី',
                'gender' => 'male', 'dob' => '1984-11-09', 'job' => 'Pediatrician',
                'dept' => self::DEPARTMENT_MEDICAL, 'type' => 'full_time', 'status' => 'employed',
                'hire' => '2020-07-01', 'schedule' => 'Mon-Fri 08:00-16:00',
                'education' => 'MD, Paediatrics Residency',
                'qualifications' => 'Child and adolescent health, immunisation',
                'license' => 'MD-KH-10752', 'expiry' => '2027-01-31',
                'salary' => 2400.00, 'allowances' => 240.00, 'supervisor' => 'STF-0001',
                'emergency_name' => 'សឿន នារី', 'created' => '2020-07-01',
            ],
            [
                'number' => 'STF-0012', 'user' => null, 'first' => 'ឈួន', 'last' => 'ស្រីនាថ',
                'gender' => 'female', 'dob' => '1989-04-03', 'job' => 'Dermatologist',
                'dept' => self::DEPARTMENT_MEDICAL, 'type' => 'part_time', 'status' => 'employed',
                'hire' => '2024-10-01', 'schedule' => 'Tue-Thu 09:00-15:00',
                'education' => 'MD, Dermatology Board Certification',
                'qualifications' => 'Clinical dermatology, skin allergy testing',
                'license' => 'MD-KH-11087', 'expiry' => '2026-12-31',
                'salary' => 2400.00, 'allowances' => 240.00, 'supervisor' => 'STF-0001',
                'emergency_name' => 'ឈួន បូរិន', 'created' => '2024-10-01',
            ],
            [
                'number' => 'STF-0013', 'user' => null, 'first' => 'ចុន', 'last' => 'ទេព',
                'gender' => 'male', 'dob' => '1987-09-17', 'job' => 'Dentist',
                'dept' => self::DEPARTMENT_MEDICAL, 'type' => 'part_time', 'status' => 'employed',
                'hire' => '2023-05-01', 'schedule' => 'Mon-Wed-Fri 10:00-16:00',
                'education' => 'Doctor of Dental Surgery',
                'qualifications' => 'Restorative dentistry, oral surgery, prophylaxis',
                'license' => 'DDS-KH-20418', 'expiry' => '2027-04-30',
                'salary' => 2300.00, 'allowances' => 230.00, 'supervisor' => 'STF-0001',
                'emergency_name' => 'ចុន សុភា', 'created' => '2023-05-01',
            ],
            [
                'number' => 'STF-0014', 'user' => null, 'first' => 'នែន', 'last' => 'សុវណ្ណ',
                'gender' => 'male', 'dob' => '1992-01-25', 'job' => 'Medical Specialist',
                'dept' => self::DEPARTMENT_MEDICAL, 'type' => 'contract', 'status' => 'probation',
                'hire' => '2026-10-15', 'schedule' => 'Mon-Fri 08:00-17:00',
                'education' => 'MD, Internal Medicine Specialisation',
                'qualifications' => 'Internal medicine, diagnostic evaluation',
                'license' => 'MD-KH-11390', 'expiry' => '2028-10-31',
                'salary' => 2600.00, 'allowances' => 260.00, 'supervisor' => 'STF-0001',
                'emergency_name' => 'នែន នារី', 'created' => '2026-10-15',
            ],
            [
                'number' => 'STF-0015', 'user' => null, 'first' => 'ពិសិដ្ឋ', 'last' => 'មុនី',
                'gender' => 'male', 'dob' => '1983-07-08', 'job' => 'Medical Specialist',
                'dept' => self::DEPARTMENT_MEDICAL, 'type' => 'full_time', 'status' => 'employed',
                'hire' => '2022-02-01', 'schedule' => 'Mon-Fri 08:00-17:00',
                'education' => 'MD, Internal Medicine Specialisation',
                'qualifications' => 'Chronic disease management, pre-operative assessment',
                'license' => 'MD-KH-10661', 'expiry' => '2027-09-30',
                'salary' => 2600.00, 'allowances' => 260.00, 'supervisor' => 'STF-0001',
                'emergency_name' => 'ពិសិដ្ឋ សុភា', 'created' => '2022-02-01',
            ],

            // ------------------------------------------------------------------
            // 2. Nursing Department (6)
            // ------------------------------------------------------------------
            [
                'number' => 'STF-0016', 'user' => 'headnurse', 'first' => 'ចន្ថា', 'last' => 'សុភា',
                'gender' => 'female', 'dob' => '1985-12-11', 'job' => 'Head Nurse',
                'dept' => self::DEPARTMENT_NURSING, 'type' => 'full_time', 'status' => 'employed',
                'hire' => '2019-01-07', 'schedule' => 'Mon-Fri 07:30-16:30',
                'education' => 'Bachelor of Nursing Science',
                'qualifications' => 'Nursing leadership, triage, ward management',
                'license' => 'RN-KH-20102', 'expiry' => '2027-06-30',
                'salary' => 1200.00, 'allowances' => 120.00, 'supervisor' => 'STF-0001',
                'emergency_name' => 'ចន្ថា បូរិន', 'created' => '2019-01-07',
            ],
            [
                'number' => 'STF-0003', 'user' => 'nurse', 'first' => 'ចន់', 'last' => 'សុភា',
                'gender' => 'female', 'dob' => '1992-07-21', 'job' => 'Senior Nurse',
                'dept' => self::DEPARTMENT_NURSING, 'type' => 'full_time', 'status' => 'employed',
                'hire' => '2020-03-10', 'schedule' => 'Mon-Fri 07:30-16:30',
                'education' => 'Bachelor of Nursing Science',
                'qualifications' => 'Acute care, medication administration, patient assessment',
                'license' => 'RN-KH-20455', 'expiry' => '2027-03-31',
                'salary' => 950.00, 'allowances' => 95.00, 'supervisor' => 'STF-0016',
                'emergency_name' => 'ចន់ វិចិត្រ', 'created' => '2020-03-10',
            ],
            [
                'number' => 'STF-0004', 'user' => 'nurse2', 'first' => 'ណុប', 'last' => 'នារី',
                'gender' => 'female', 'dob' => '1995-11-05', 'job' => 'Registered Nurse',
                'dept' => self::DEPARTMENT_NURSING, 'type' => 'part_time', 'status' => 'employed',
                'hire' => '2022-09-01', 'schedule' => 'Mon-Fri 14:30-21:30',
                'education' => 'Associate Degree in Nursing',
                'qualifications' => 'Medication administration, wound care, patient education',
                'license' => 'RN-KH-20771', 'expiry' => '2026-08-31',
                'salary' => 750.00, 'allowances' => 75.00, 'supervisor' => 'STF-0016',
                'emergency_name' => 'ណុប សុវណ្ណ', 'created' => '2022-09-01',
            ],
            [
                'number' => 'STF-0017', 'user' => null, 'first' => 'សុខា', 'last' => 'នារី',
                'gender' => 'female', 'dob' => '1997-03-19', 'job' => 'Registered Nurse',
                'dept' => self::DEPARTMENT_NURSING, 'type' => 'full_time', 'status' => 'on_leave',
                'hire' => '2026-08-01', 'schedule' => 'Mon-Fri 07:30-16:30',
                'education' => 'Bachelor of Nursing Science',
                'qualifications' => 'Maternity care, neonatal observation',
                'license' => 'RN-KH-21145', 'expiry' => '2028-07-31',
                'salary' => 750.00, 'allowances' => 75.00, 'supervisor' => 'STF-0016',
                'emergency_name' => 'សុខា បូរិន', 'created' => '2026-08-01',
            ],
            [
                'number' => 'STF-0018', 'user' => null, 'first' => 'វណ្ណា', 'last' => 'បូរិន',
                'gender' => 'female', 'dob' => '1998-06-30', 'job' => 'Assistant Nurse',
                'dept' => self::DEPARTMENT_NURSING, 'type' => 'full_time', 'status' => 'employed',
                'hire' => '2025-02-01', 'schedule' => 'Mon-Fri 07:30-16:30',
                'education' => 'Diploma in Nursing',
                'qualifications' => 'Basic nursing care, vital signs, patient mobility',
                'license' => 'AN-KH-30218', 'expiry' => '2027-01-31',
                'salary' => 550.00, 'allowances' => 55.00, 'supervisor' => 'STF-0016',
                'emergency_name' => 'វណ្ណា សុវណ្ណ', 'created' => '2025-02-01',
            ],
            [
                'number' => 'STF-0019', 'user' => null, 'first' => 'សុវណ្ណ', 'last' => 'ចន្ថា',
                'gender' => 'female', 'dob' => '2000-10-12', 'job' => 'Nursing Assistant',
                'dept' => self::DEPARTMENT_NURSING, 'type' => 'full_time', 'status' => 'employed',
                'hire' => '2026-05-01', 'schedule' => 'Mon-Fri 07:30-16:30',
                'education' => 'Certificate in Nursing Assistance',
                'qualifications' => 'Personal care, feeding support, hygiene assistance',
                'license' => null, 'expiry' => null,
                'salary' => 420.00, 'allowances' => 42.00, 'supervisor' => 'STF-0016',
                'emergency_name' => 'សុវណ្ណ សុខ', 'created' => '2026-05-01',
            ],

            // ------------------------------------------------------------------
            // 3. Pharmacy Department (3)
            // ------------------------------------------------------------------
            [
                'number' => 'STF-0006', 'user' => 'pharmacist', 'first' => 'ព្រាប', 'last' => 'មុនី',
                'gender' => 'female', 'dob' => '1988-12-25', 'job' => 'Chief Pharmacist',
                'dept' => self::DEPARTMENT_PHARMACY, 'type' => 'full_time', 'status' => 'employed',
                'hire' => '2019-10-05', 'schedule' => 'Mon-Fri 08:00-17:00',
                'education' => 'Bachelor of Pharmacy',
                'qualifications' => 'Dispensing, stock control, clinical pharmacy review',
                'license' => 'PH-KH-30812', 'expiry' => '2027-06-30',
                'salary' => 1100.00, 'allowances' => 110.00, 'supervisor' => 'STF-0001',
                'emergency_name' => 'ព្រាប សុភា', 'created' => '2019-10-05',
            ],
            [
                'number' => 'STF-0020', 'user' => null, 'first' => 'មុនី', 'last' => 'ព្រាប',
                'gender' => 'female', 'dob' => '1993-05-16', 'job' => 'Pharmacist',
                'dept' => self::DEPARTMENT_PHARMACY, 'type' => 'full_time', 'status' => 'employed',
                'hire' => '2024-01-15', 'schedule' => 'Mon-Fri 08:00-17:00',
                'education' => 'Bachelor of Pharmacy',
                'qualifications' => 'Prescription review, patient counselling',
                'license' => 'PH-KH-31120', 'expiry' => '2027-12-31',
                'salary' => 850.00, 'allowances' => 85.00, 'supervisor' => 'STF-0006',
                'emergency_name' => 'មុនី សុវណ្ណ', 'created' => '2024-01-15',
            ],
            [
                'number' => 'STF-0021', 'user' => null, 'first' => 'បូរិន', 'last' => 'មាស',
                'gender' => 'male', 'dob' => '1999-08-08', 'job' => 'Pharmacy Assistant',
                'dept' => self::DEPARTMENT_PHARMACY, 'type' => 'part_time', 'status' => 'employed',
                'hire' => '2026-06-01', 'schedule' => 'Mon-Fri 12:00-17:00',
                'education' => 'Certificate in Pharmacy Assistance',
                'qualifications' => 'Stock rotation, labelling, counter service',
                'license' => null, 'expiry' => null,
                'salary' => 480.00, 'allowances' => 48.00, 'supervisor' => 'STF-0006',
                'emergency_name' => 'បូរិន នារី', 'created' => '2026-06-01',
            ],

            // ------------------------------------------------------------------
            // 4. Laboratory & Diagnostics (4)
            // ------------------------------------------------------------------
            [
                'number' => 'STF-0022', 'user' => null, 'first' => 'សុខ', 'last' => 'ហេង',
                'gender' => 'male', 'dob' => '1981-03-05', 'job' => 'Laboratory Manager',
                'dept' => self::DEPARTMENT_LABORATORY, 'type' => 'full_time', 'status' => 'employed',
                'hire' => '2020-11-01', 'schedule' => 'Mon-Fri 07:00-15:00',
                'education' => 'Bachelor of Medical Laboratory Science',
                'qualifications' => 'Laboratory quality management, haematology, biochemistry',
                'license' => 'ML-KH-40110', 'expiry' => '2027-08-31',
                'salary' => 1000.00, 'allowances' => 100.00, 'supervisor' => 'STF-0001',
                'emergency_name' => 'សុខ សុភា', 'created' => '2020-11-01',
            ],
            [
                'number' => 'STF-0023', 'user' => null, 'first' => 'លី', 'last' => 'ទូច',
                'gender' => 'male', 'dob' => '1990-09-27', 'job' => 'Medical Laboratory Technologist',
                'dept' => self::DEPARTMENT_LABORATORY, 'type' => 'full_time', 'status' => 'employed',
                'hire' => '2023-08-01', 'schedule' => 'Mon-Fri 07:00-15:00',
                'education' => 'Bachelor of Medical Laboratory Science',
                'qualifications' => 'Clinical chemistry, microbiology, specimen processing',
                'license' => 'ML-KH-40377', 'expiry' => '2026-11-30',
                'salary' => 700.00, 'allowances' => 70.00, 'supervisor' => 'STF-0022',
                'emergency_name' => 'លី បូរិន', 'created' => '2023-08-01',
            ],
            [
                'number' => 'STF-0024', 'user' => null, 'first' => 'សំ', 'last' => 'កែវ',
                'gender' => 'male', 'dob' => '1997-12-03', 'job' => 'Laboratory Assistant',
                'dept' => self::DEPARTMENT_LABORATORY, 'type' => 'full_time', 'status' => 'employed',
                'hire' => '2026-07-01', 'schedule' => 'Mon-Fri 07:00-15:00',
                'education' => 'Diploma in Laboratory Techniques',
                'qualifications' => 'Specimen reception, equipment cleaning, stock of reagents',
                'license' => null, 'expiry' => null,
                'salary' => 450.00, 'allowances' => 45.00, 'supervisor' => 'STF-0022',
                'emergency_name' => 'សំ សុភា', 'created' => '2026-07-01',
            ],
            [
                'number' => 'STF-0025', 'user' => null, 'first' => 'យ៉ែម', 'last' => 'ស្រីនាថ',
                'gender' => 'female', 'dob' => '1994-02-20', 'job' => 'Radiology Technician',
                'dept' => self::DEPARTMENT_LABORATORY, 'type' => 'full_time', 'status' => 'employed',
                'hire' => '2022-10-01', 'schedule' => 'Mon-Fri 08:00-16:00',
                'education' => 'Bachelor of Radiology Technology',
                'qualifications' => 'X-ray imaging, patient positioning, radiation safety',
                'license' => 'RT-KH-50231', 'expiry' => '2027-03-31',
                'salary' => 720.00, 'allowances' => 72.00, 'supervisor' => 'STF-0022',
                'emergency_name' => 'យ៉ែម ចន្ថា', 'created' => '2022-10-01',
            ],

            // ------------------------------------------------------------------
            // 5. Administration & Reception (8)
            // ------------------------------------------------------------------
            [
                'number' => 'STF-0026', 'user' => null, 'first' => 'ទេព', 'last' => 'ចាន់ថា',
                'gender' => 'male', 'dob' => '1979-05-14', 'job' => 'Clinic Administrator',
                'dept' => self::DEPARTMENT_ADMINISTRATION, 'type' => 'full_time', 'status' => 'employed',
                'hire' => '2018-06-01', 'schedule' => 'Mon-Fri 08:00-17:00',
                'education' => 'Master of Business Administration',
                'qualifications' => 'Healthcare administration, operations, compliance',
                'license' => null, 'expiry' => null,
                'salary' => 1300.00, 'allowances' => 130.00, 'supervisor' => 'STF-0001',
                'emergency_name' => 'ទេព សុភា', 'created' => '2018-06-01',
            ],
            [
                'number' => 'STF-0027', 'user' => null, 'first' => 'ចាន់', 'last' => 'ដារា',
                'gender' => 'male', 'dob' => '1985-10-09', 'job' => 'HR Manager',
                'dept' => self::DEPARTMENT_ADMINISTRATION, 'type' => 'full_time', 'status' => 'employed',
                'hire' => '2021-09-01', 'schedule' => 'Mon-Fri 08:00-17:00',
                'education' => 'Bachelor of Human Resource Management',
                'qualifications' => 'Recruitment, payroll, employee relations',
                'license' => null, 'expiry' => null,
                'salary' => 1000.00, 'allowances' => 100.00, 'supervisor' => 'STF-0026',
                'emergency_name' => 'ចាន់ នារី', 'created' => '2021-09-01',
            ],
            [
                'number' => 'STF-0028', 'user' => null, 'first' => 'ដារា', 'last' => 'វណ្ណា',
                'gender' => 'female', 'dob' => '1993-04-22', 'job' => 'HR Officer',
                'dept' => self::DEPARTMENT_ADMINISTRATION, 'type' => 'full_time', 'status' => 'employed',
                'hire' => '2024-05-01', 'schedule' => 'Mon-Fri 08:00-17:00',
                'education' => 'Bachelor of Human Resource Management',
                'qualifications' => 'Onboarding, leave administration, records',
                'license' => null, 'expiry' => null,
                'salary' => 650.00, 'allowances' => 65.00, 'supervisor' => 'STF-0027',
                'emergency_name' => 'ដារា បូរិន', 'created' => '2024-05-01',
            ],
            [
                'number' => 'STF-0005', 'user' => 'receptionist', 'first' => 'មាស', 'last' => 'បូរិន',
                'gender' => 'female', 'dob' => '1990-05-08', 'job' => 'Receptionist',
                'dept' => self::DEPARTMENT_ADMINISTRATION, 'type' => 'full_time', 'status' => 'employed',
                'hire' => '2021-01-20', 'schedule' => 'Mon-Fri 07:30-16:30',
                'education' => 'Bachelor of Business Administration',
                'qualifications' => 'Front desk, appointment scheduling, patient registration',
                'license' => null, 'expiry' => null,
                'salary' => 500.00, 'allowances' => 50.00, 'supervisor' => 'STF-0026',
                'emergency_name' => 'មាស សុវណ្ណ', 'created' => '2021-01-20',
            ],
            [
                'number' => 'STF-0029', 'user' => null, 'first' => 'វណ្ណា', 'last' => 'ព្រាប',
                'gender' => 'female', 'dob' => '1996-07-19', 'job' => 'Receptionist',
                'dept' => self::DEPARTMENT_ADMINISTRATION, 'type' => 'full_time', 'status' => 'employed',
                'hire' => '2026-04-01', 'schedule' => 'Mon-Fri 12:00-20:00',
                'education' => 'Diploma in Office Administration',
                'qualifications' => 'Patient check-in, billing handoff, phone triage',
                'license' => null, 'expiry' => null,
                'salary' => 500.00, 'allowances' => 50.00, 'supervisor' => 'STF-0026',
                'emergency_name' => 'វណ្ណា ចន្ថា', 'created' => '2026-04-01',
            ],
            [
                'number' => 'STF-0030', 'user' => null, 'first' => 'ព្រាប', 'last' => 'មុនី',
                'gender' => 'female', 'dob' => '1991-11-02', 'job' => 'Patient Service Officer',
                'dept' => self::DEPARTMENT_ADMINISTRATION, 'type' => 'full_time', 'status' => 'employed',
                'hire' => '2022-08-01', 'schedule' => 'Mon-Fri 08:00-17:00',
                'education' => 'Bachelor of Public Health',
                'qualifications' => 'Patient experience, complaints handling, service coordination',
                'license' => null, 'expiry' => null,
                'salary' => 550.00, 'allowances' => 55.00, 'supervisor' => 'STF-0026',
                'emergency_name' => 'ព្រាប សុខ', 'created' => '2022-08-01',
            ],
            [
                'number' => 'STF-0031', 'user' => null, 'first' => 'មុនី', 'last' => 'សុភា',
                'gender' => 'female', 'dob' => '1989-01-15', 'job' => 'Billing Officer',
                'dept' => self::DEPARTMENT_ADMINISTRATION, 'type' => 'full_time', 'status' => 'employed',
                'hire' => '2020-04-01', 'schedule' => 'Mon-Fri 08:00-17:00',
                'education' => 'Bachelor of Accounting',
                'qualifications' => 'Invoicing, insurance claims, payment posting',
                'license' => null, 'expiry' => null,
                'salary' => 600.00, 'allowances' => 60.00, 'supervisor' => 'STF-0026',
                'emergency_name' => 'មុនី បូរិន', 'created' => '2020-04-01',
            ],
            [
                'number' => 'STF-0032', 'user' => null, 'first' => 'សុភា', 'last' => 'នារី',
                'gender' => 'female', 'dob' => '1987-03-28', 'job' => 'Accountant',
                'dept' => self::DEPARTMENT_ADMINISTRATION, 'type' => 'full_time', 'status' => 'employed',
                'hire' => '2019-11-01', 'schedule' => 'Mon-Fri 08:00-17:00',
                'education' => 'Bachelor of Accounting and Finance',
                'qualifications' => 'Bookkeeping, payroll, financial reporting',
                'license' => null, 'expiry' => null,
                'salary' => 800.00, 'allowances' => 80.00, 'supervisor' => 'STF-0026',
                'emergency_name' => 'សុភា ចន្ថា', 'created' => '2019-11-01',
            ],

            // ------------------------------------------------------------------
            // 6. Operations & Support (10)
            // ------------------------------------------------------------------
            [
                'number' => 'STF-0033', 'user' => null, 'first' => 'នារី', 'last' => 'បូរិន',
                'gender' => 'male', 'dob' => '1982-06-16', 'job' => 'Operations Manager',
                'dept' => self::DEPARTMENT_OPERATIONS, 'type' => 'full_time', 'status' => 'employed',
                'hire' => '2020-02-01', 'schedule' => 'Mon-Fri 08:00-17:00',
                'education' => 'Bachelor of Management',
                'qualifications' => 'Facilities operations, vendor management, fleet and security',
                'license' => null, 'expiry' => null,
                'salary' => 1100.00, 'allowances' => 110.00, 'supervisor' => 'STF-0001',
                'emergency_name' => 'នារី សុភា', 'created' => '2020-02-01',
            ],
            [
                'number' => 'STF-0034', 'user' => null, 'first' => 'បូរិន', 'last' => 'សុវណ្ណ',
                'gender' => 'male', 'dob' => '1990-08-23', 'job' => 'Inventory Officer',
                'dept' => self::DEPARTMENT_OPERATIONS, 'type' => 'full_time', 'status' => 'employed',
                'hire' => '2022-01-10', 'schedule' => 'Mon-Fri 07:00-15:00',
                'education' => 'Diploma in Supply Chain Management',
                'qualifications' => 'Stock counting, replenishment, cold-chain monitoring',
                'license' => null, 'expiry' => null,
                'salary' => 650.00, 'allowances' => 65.00, 'supervisor' => 'STF-0033',
                'emergency_name' => 'បូរិន នារី', 'created' => '2022-01-10',
            ],
            [
                'number' => 'STF-0035', 'user' => null, 'first' => 'សុវណ្ណ', 'last' => 'ចន្ថា',
                'gender' => 'male', 'dob' => '1988-12-07', 'job' => 'Procurement Officer',
                'dept' => self::DEPARTMENT_OPERATIONS, 'type' => 'full_time', 'status' => 'employed',
                'hire' => '2021-07-01', 'schedule' => 'Mon-Fri 08:00-17:00',
                'education' => 'Bachelor of Procurement and Logistics',
                'qualifications' => 'Purchasing, supplier contracts, purchase orders',
                'license' => null, 'expiry' => null,
                'salary' => 650.00, 'allowances' => 65.00, 'supervisor' => 'STF-0033',
                'emergency_name' => 'សុវណ្ណ សុខ', 'created' => '2021-07-01',
            ],
            [
                'number' => 'STF-0036', 'user' => null, 'first' => 'ចន្ថា', 'last' => 'សុខ',
                'gender' => 'female', 'dob' => '1986-09-12', 'job' => 'Housekeeping Supervisor',
                'dept' => self::DEPARTMENT_OPERATIONS, 'type' => 'full_time', 'status' => 'employed',
                'hire' => '2023-04-01', 'schedule' => 'Mon-Fri 07:00-15:00',
                'education' => 'High school diploma',
                'qualifications' => 'Cleaning standards, infection control, supervision',
                'license' => null, 'expiry' => null,
                'salary' => 500.00, 'allowances' => 50.00, 'supervisor' => 'STF-0033',
                'emergency_name' => 'ចន្ថា បូរិន', 'created' => '2023-04-01',
            ],
            [
                'number' => 'STF-0007', 'user' => null, 'first' => 'ហេង', 'last' => 'វណ្ណា',
                'gender' => 'male', 'dob' => '1998-01-15', 'job' => 'Cleaner',
                'dept' => self::DEPARTMENT_OPERATIONS, 'type' => 'full_time', 'status' => 'employed',
                'hire' => '2023-02-12', 'schedule' => 'Mon-Fri 07:00-15:00',
                'education' => 'Primary school',
                'qualifications' => 'Building cleaning, waste handling, hygiene',
                'license' => null, 'expiry' => null,
                'salary' => 320.00, 'allowances' => 32.00, 'supervisor' => 'STF-0036',
                'emergency_name' => 'ហេង ចន្ថា', 'created' => '2023-02-12',
            ],
            [
                'number' => 'STF-0037', 'user' => null, 'first' => 'សុខ', 'last' => 'លី',
                'gender' => 'male', 'dob' => '2001-04-28', 'job' => 'Cleaner',
                'dept' => self::DEPARTMENT_OPERATIONS, 'type' => 'contract', 'status' => 'probation',
                'hire' => '2026-11-01', 'schedule' => 'Mon-Fri 07:00-15:00',
                'education' => 'High school diploma',
                'qualifications' => 'Building cleaning, waste segregation',
                'license' => null, 'expiry' => null,
                'salary' => 320.00, 'allowances' => 32.00, 'supervisor' => 'STF-0036',
                'emergency_name' => 'សុខ នារី', 'created' => '2026-11-01',
            ],
            [
                'number' => 'STF-0038', 'user' => null, 'first' => 'លី', 'last' => 'ហេង',
                'gender' => 'male', 'dob' => '1984-10-30', 'job' => 'Security Supervisor',
                'dept' => self::DEPARTMENT_OPERATIONS, 'type' => 'full_time', 'status' => 'employed',
                'hire' => '2021-05-01', 'schedule' => 'Mon-Fri 18:00-06:00',
                'education' => 'High school diploma',
                'qualifications' => 'Access control, CCTV monitoring, incident response',
                'license' => null, 'expiry' => null,
                'salary' => 550.00, 'allowances' => 55.00, 'supervisor' => 'STF-0033',
                'emergency_name' => 'លី សុភា', 'created' => '2021-05-01',
            ],
            [
                'number' => 'STF-0008', 'user' => null, 'first' => 'ទូច', 'last' => 'សុវណ្ណ',
                'gender' => 'male', 'dob' => '1994-06-11', 'job' => 'Security Guard',
                'dept' => self::DEPARTMENT_OPERATIONS, 'type' => 'contract', 'status' => 'employed',
                'hire' => '2022-05-01', 'schedule' => 'Daily 18:00-06:00',
                'education' => 'Secondary school',
                'qualifications' => 'Premises patrol, visitor management, emergency response',
                'license' => null, 'expiry' => null,
                'salary' => 380.00, 'allowances' => 38.00, 'supervisor' => 'STF-0038',
                'emergency_name' => 'ទូច នារី', 'created' => '2022-05-01',
            ],
            [
                'number' => 'STF-0039', 'user' => null, 'first' => 'ហេង', 'last' => 'ទូច',
                'gender' => 'male', 'dob' => '1997-02-14', 'job' => 'Security Guard',
                'dept' => self::DEPARTMENT_OPERATIONS, 'type' => 'contract', 'status' => 'employed',
                'hire' => '2026-09-15', 'schedule' => 'Daily 06:00-18:00',
                'education' => 'Secondary school',
                'qualifications' => 'Premises patrol, gate control, incident logging',
                'license' => null, 'expiry' => null,
                'salary' => 380.00, 'allowances' => 38.00, 'supervisor' => 'STF-0038',
                'emergency_name' => 'ហេង បូរិន', 'created' => '2026-09-15',
            ],
            [
                'number' => 'STF-0040', 'user' => null, 'first' => 'ទូច', 'last' => 'សំ',
                'gender' => 'male', 'dob' => '1989-07-05', 'job' => 'Maintenance Technician',
                'dept' => self::DEPARTMENT_OPERATIONS, 'type' => 'full_time', 'status' => 'employed',
                'hire' => '2023-10-01', 'schedule' => 'Mon-Fri 08:00-16:00',
                'education' => 'Vocational certificate in Electrical Maintenance',
                'qualifications' => 'Building maintenance, HVAC, plumbing, electrical repairs',
                'license' => null, 'expiry' => null,
                'salary' => 600.00, 'allowances' => 60.00, 'supervisor' => 'STF-0033',
                'emergency_name' => 'ទូច សុភា', 'created' => '2023-10-01',
            ],
        ];
    }

    /**
     * @return array<int, array{title: string, category: string, original_name: string}>
     */
    private function documentsFor(Staff $staff): array
    {
        $name = $staff->full_name;

        $documents = [
            [
                'title' => 'Curriculum Vitae',
                'category' => StaffDocument::CATEGORY_CV,
                'original_name' => 'CV.pdf',
            ],
            [
                'title' => 'Employment Contract',
                'category' => StaffDocument::CATEGORY_CONTRACT,
                'original_name' => 'Employment-Contract.pdf',
            ],
        ];

        if ($staff->license_number !== null) {
            $documents[] = [
                'title' => 'Professional License',
                'category' => StaffDocument::CATEGORY_LICENSE,
                'original_name' => 'License-'.$staff->license_number.'.pdf',
            ];
        }

        if (in_array($staff->job_title, ['Medical Director', 'General Practitioner', 'Cardiologist', 'Pediatrician', 'Dermatologist', 'Dentist', 'Medical Specialist'], true)) {
            $documents[] = [
                'title' => 'Board Certification',
                'category' => StaffDocument::CATEGORY_CERTIFICATE,
                'original_name' => 'Board-Certification.pdf',
            ];
        }

        return $documents;
    }

    private function makePdf(string $title): string
    {
        $text = $this->pdfEscape($title);
        $content = "BT /F1 16 Tf 72 720 Td (".$text.') Tj ET';

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            4 => '<< /Length '.strlen($content)." >>\nstream\n".$content."\nendstream",
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number." 0 obj\n".$body."\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".count($objects)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        $pdf .= "trailer\n<< /Size ".count($objects).' /Root 1 0 R >>'."\n";
        $pdf .= 'startxref'."\n".$xref."\n%%EOF";

        return $pdf;
    }

    private function pdfSize(): int
    {
        return 1024;
    }

    private function pdfEscape(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }

    private function phone(string $seed): string
    {
        $number = (int) substr(md5($seed), 0, 8);

        return '0'.substr((string) (10000000 + ($number % 90000000)), 0, 8);
    }

    private function address(string $seed): string
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
