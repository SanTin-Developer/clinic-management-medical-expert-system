<?php

namespace Database\Seeders;

use App\Models\Admission;
use App\Models\AdmissionCharge;
use App\Models\AdmissionDeposit;
use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\Appointment;
use App\Models\Article;
use App\Models\BillableService;
use App\Models\ClinicHoliday;
use App\Models\ContactMessage;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ConversationMessageRead;
use App\Models\ConversationParticipant;
use App\Models\ExpertEvaluationAudit;
use App\Models\GalleryItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InventoryItem;
use App\Models\MediaAsset;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\PatientDocument;
use App\Models\Payment;
use App\Models\PharmacySale;
use App\Models\PharmacySaleItem;
use App\Models\PublicTeamProfile;
use App\Models\Service;
use App\Models\Shift;
use App\Models\ShiftTemplate;
use App\Models\StaffInvitation;
use App\Models\StockMovement;
use App\Models\SystemAlert;
use App\Models\User;
use App\Services\Pharmacy\InventoryService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fills every remaining admin and website page with production-like demo data:
 * attendance, scheduling, leave, announcements, notifications, system alerts,
 * audit logs, team chat, pharmacy POS sales, inpatient billing, holidays,
 * expert-system audits, staff invitations, contact messages, media library,
 * patient documents and a recent patient/appointment/revenue history.
 *
 * Runs after KhmerDemoDataSeeder, InpatientDemoDataSeeder and
 * StaffDirectorySeeder. Every step is idempotent: records are keyed on stable
 * identifiers and existing rows are never overwritten.
 */
class ProductionLikeDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedClinicHolidays();
            $this->seedBillableServices();
            $this->seedShiftsAndAttendance();
            $this->seedLeaveRequests();
            $this->seedAnnouncements();
            $this->seedNotifications();
            $this->seedSystemAlerts();
            $this->seedActivityLogs();
            $this->seedConversations();
            $this->seedExpertAudits();
            $this->seedStaffInvitations();
            $this->seedContactMessages();
            $this->seedMediaAssets();
            $this->seedPatientDocuments();
            $this->seedInventoryDemo();
            $this->seedPharmacySales();
            $this->seedAdmissionBilling();
            $this->seedRecentActivity();
        });
    }

    // ----------------------------------------------------------------------
    // Reference data
    // ----------------------------------------------------------------------

    private function seedClinicHolidays(): void
    {
        $holidays = [
            ['2026-01-01', "New Year's Day", true],
            ['2026-04-13', 'Khmer New Year', false],
            ['2026-05-01', 'Labour Day', true],
            ['2026-09-21', 'Pchum Ben (Festival of the Dead)', false],
            ['2026-10-29', "King's Coronation Day", true],
            ['2026-11-09', 'Independence Day', true],
            ['2026-12-10', 'Human Rights Day', true],
            ['2027-01-01', "New Year's Day", true],
        ];

        foreach ($holidays as [$date, $name, $recurring]) {
            $attributes = [
                'name' => $name,
                'is_recurring' => $recurring,
                'notes' => 'Clinic holiday — absence finalization skips this date.',
            ];

            $holiday = ClinicHoliday::query()->whereDate('holiday_date', $date)->first();

            if ($holiday !== null) {
                $holiday->update($attributes);
            } else {
                ClinicHoliday::create(array_merge(['holiday_date' => $date], $attributes));
            }
        }
    }

    private function seedBillableServices(): void
    {
        $services = [
            ['code' => 'LAB-CBC', 'name' => 'Complete Blood Count', 'category' => 'lab', 'unit' => 'test', 'price' => 8.00],
            ['code' => 'LAB-BG', 'name' => 'Blood Glucose (Fasting)', 'category' => 'lab', 'unit' => 'test', 'price' => 5.00],
            ['code' => 'LAB-UA', 'name' => 'Urinalysis', 'category' => 'lab', 'unit' => 'test', 'price' => 4.00],
            ['code' => 'LAB-LIPID', 'name' => 'Lipid Profile', 'category' => 'lab', 'unit' => 'test', 'price' => 12.00],
            ['code' => 'IMG-CXR', 'name' => 'Chest X-Ray', 'category' => 'imaging', 'unit' => 'study', 'price' => 25.00],
            ['code' => 'IMG-US', 'name' => 'Abdominal Ultrasound', 'category' => 'imaging', 'unit' => 'study', 'price' => 40.00],
            ['code' => 'PROC-DRESS', 'name' => 'Wound Dressing', 'category' => 'procedure', 'unit' => 'procedure', 'price' => 10.00],
            ['code' => 'PROC-INJ', 'name' => 'Injection (IM/SC)', 'category' => 'procedure', 'unit' => 'dose', 'price' => 5.00],
            ['code' => 'PROC-IV', 'name' => 'IV Cannulation', 'category' => 'procedure', 'unit' => 'procedure', 'price' => 8.00],
            ['code' => 'NUR-CARE', 'name' => 'Nursing Care (per shift)', 'category' => 'nursing', 'unit' => 'shift', 'price' => 15.00],
            ['code' => 'NUR-MED', 'name' => 'Medication Administration', 'category' => 'nursing', 'unit' => 'dose', 'price' => 5.00],
            ['code' => 'CONS-SPEC', 'name' => 'Specialist Consultation', 'category' => 'consultation', 'unit' => 'visit', 'price' => 20.00],
            ['code' => 'CONS-FUP', 'name' => 'Follow-up Consultation', 'category' => 'consultation', 'unit' => 'visit', 'price' => 10.00],
            ['code' => 'SUP-GLOVES', 'name' => 'Surgical Gloves (pair)', 'category' => 'supply', 'unit' => 'pair', 'price' => 2.00],
            ['code' => 'SUP-SYRINGE', 'name' => 'Syringe 5ml', 'category' => 'supply', 'unit' => 'piece', 'price' => 1.00],
            ['code' => 'ROOM-GEN', 'name' => 'General Ward (per day)', 'category' => 'room', 'unit' => 'day', 'price' => 15.00],
        ];

        foreach ($services as $service) {
            BillableService::firstOrCreate(
                ['code' => $service['code']],
                [
                    'name' => $service['name'],
                    'category' => $service['category'],
                    'unit' => $service['unit'],
                    'default_price' => $service['price'],
                    'is_active' => true,
                    'description' => $service['name'].' provided by RoyalCare Clinic.',
                    'requires_approval' => false,
                    'price_source' => 'catalog',
                ],
            );
        }
    }

    // ----------------------------------------------------------------------
    // Attendance & scheduling
    // ----------------------------------------------------------------------

    private function seedShiftsAndAttendance(): void
    {
        $timezone = (string) config('attendance.timezone', 'Asia/Phnom_Penh');
        $templates = ShiftTemplate::query()->get()->keyBy('type');
        $admin = User::where('username', 'admin')->first();

        $assignments = [
            'doctor' => 'day',
            'doctor2' => 'evening',
            'nurse' => 'day',
            'nurse2' => 'evening',
            'headnurse' => 'day',
            'receptionist' => 'day',
            'pharmacist' => 'day',
        ];

        $users = User::whereIn('username', array_keys($assignments))->get()->keyBy('username');

        if ($users->isEmpty() || $templates->isEmpty()) {
            return;
        }

        $today = Carbon::today($timezone);

        foreach ($users as $username => $user) {
            $template = $templates[$assignments[$username]];

            foreach (range(-14, 7) as $offset) {
                $date = $today->copy()->addDays($offset);

                $exists = Shift::query()
                    ->where('user_id', $user->id)
                    ->whereDate('shift_date', $date->toDateString())
                    ->where('shift_template_id', $template->id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $isPast = $offset < 0;

                Shift::create([
                    'user_id' => $user->id,
                    'shift_template_id' => $template->id,
                    'shift_date' => $date->toDateString(),
                    'start_time' => $template->start_time,
                    'end_time' => $template->end_time,
                    'crosses_midnight' => (bool) $template->crosses_midnight,
                    'type' => $template->type,
                    'status' => $isPast ? 'completed' : 'scheduled',
                    'published_at' => $this->pseudoRandom($username.':'.$date->toDateString().':pub', 0, 9) < 8
                        ? $date->copy()->subDay()->setTime(18, 0)
                        : null,
                    'notes' => null,
                    'created_by' => $admin?->id,
                ]);
            }

            foreach (range(-14, -1) as $offset) {
                $date = $today->copy()->addDays($offset);

                $shift = Shift::query()
                    ->where('user_id', $user->id)
                    ->where('shift_date', $date->toDateString())
                    ->where('shift_template_id', $template->id)
                    ->first();

                $start = Carbon::parse($date->toDateString().' '.$template->start_time, $timezone)->utc();
                $end = Carbon::parse($date->toDateString().' '.$template->end_time, $timezone)->utc();

                if ($template->crosses_midnight) {
                    $end->addDay();
                }

                $status = $this->attendanceStatus($user->id, $date->toDateString());

                // Most staff are off on a clinic holiday, but a skeleton team
                // still rotates through so the rota stays realistic.
                if ($this->isClinicHoliday($date) && $this->pseudoRandom($username.':'.$date->toDateString().':holiday', 0, 99) < 60) {
                    $status = 'holiday';
                }

                $late = $status === 'late' ? $this->pseudoRandom($username.':'.$date->toDateString().':late', 5, 45) : 0;
                $hasClock = ! in_array($status, ['absent', 'on_leave', 'holiday'], true);

                $clockIn = $hasClock ? $start->copy()->addMinutes($late + $this->pseudoRandom($username.':'.$date->toDateString().':in', 0, 5)) : null;
                $missing = $hasClock && $this->pseudoRandom($username.':'.$date->toDateString().':missing', 0, 99) < 3;
                $clockOut = $hasClock && ! $missing
                    ? $end->copy()->addMinutes($this->pseudoRandom($username.':'.$date->toDateString().':out', 0, 15))
                    : null;

                DB::table('attendance_records')->updateOrInsert(
                    ['user_id' => $user->id, 'work_date' => $date->toDateString()],
                    [
                        'shift_id' => $shift?->id,
                        'shift_template_id' => $template->id,
                        'clock_in' => $clockIn,
                        'clock_out' => $clockOut,
                        'scheduled_start' => $start,
                        'scheduled_end' => $end,
                        'status' => $status,
                        'late_minutes' => $late,
                        'late_band' => $late > 0 ? $this->lateBand($late) : null,
                        'clock_in_source' => $clockIn !== null ? 'qr' : null,
                        'clock_out_source' => $clockOut !== null ? 'qr' : null,
                        'missing_clock_out' => $missing,
                        'finalized_at' => $clockOut,
                        'notes' => null,
                        'approved_by' => $admin?->id,
                        'approved_at' => $clockOut,
                        'created_at' => $clockIn ?? now(),
                        'updated_at' => now(),
                    ],
                );
            }
        }
    }

    private function isClinicHoliday(Carbon $date): bool
    {
        return DB::table('clinic_holidays')->whereDate('holiday_date', $date->toDateString())->exists();
    }

    private function attendanceStatus(int $userId, string $date): string
    {
        $r = $this->pseudoRandom($userId.':'.$date, 0, 99);

        return match (true) {
            $r < 78 => 'present',
            $r < 88 => 'late',
            $r < 93 => 'absent',
            $r < 97 => 'on_leave',
            default => 'holiday',
        };
    }

    private function lateBand(int $minutes): ?string
    {
        if ($minutes <= 0) {
            return null;
        }

        foreach ((array) config('attendance.late_bands', []) as $band) {
            if ($minutes >= $band['min'] && $minutes <= $band['max']) {
                return $band['label'];
            }
        }

        return 'Late 30+';
    }

    // ----------------------------------------------------------------------
    // Leave management
    // ----------------------------------------------------------------------

    private function seedLeaveRequests(): void
    {
        $admin = User::where('username', 'admin')->first();
        $today = Carbon::today();

        $requests = [
            ['user' => 'doctor', 'type' => 'annual', 'start' => -30, 'end' => -25, 'status' => 'approved', 'reason' => 'Family vacation.'],
            ['user' => 'nurse', 'type' => 'sick', 'start' => -10, 'end' => -9, 'status' => 'approved', 'reason' => 'Influenza with fever.'],
            ['user' => 'nurse2', 'type' => 'maternity', 'start' => 20, 'end' => 110, 'status' => 'pending', 'reason' => 'Maternity leave request.'],
            ['user' => 'receptionist', 'type' => 'annual', 'start' => 10, 'end' => 14, 'status' => 'pending', 'reason' => 'Personal travel.'],
            ['user' => 'pharmacist', 'type' => 'emergency', 'start' => -5, 'end' => -4, 'status' => 'rejected', 'reason' => 'Family emergency.'],
            ['user' => 'doctor2', 'type' => 'unpaid', 'start' => -20, 'end' => -18, 'status' => 'approved', 'reason' => 'Personal matters.'],
            ['user' => 'headnurse', 'type' => 'annual', 'start' => 30, 'end' => 34, 'status' => 'pending', 'reason' => 'Rest and recovery.'],
        ];

        foreach ($requests as $request) {
            $user = User::where('username', $request['user'])->first();

            if ($user === null) {
                continue;
            }

            $start = $today->copy()->addDays($request['start'])->toDateString();
            $end = $today->copy()->addDays($request['end'])->toDateString();
            $days = $today->copy()->addDays($request['start'])->diffInDays($today->copy()->addDays($request['end'])) + 1;

            $attributes = [
                'user_id' => $user->id,
                'type' => $request['type'],
                'start_date' => $start,
                'end_date' => $end,
                'days' => $days,
                'reason' => $request['reason'],
                'status' => $request['status'],
            ];

            if ($request['status'] === 'approved' || $request['status'] === 'rejected') {
                $attributes['reviewed_by'] = $admin?->id;
                $attributes['reviewed_at'] = $today->copy()->addDays($request['start'] - 2)->setTime(10, 0);
                $attributes['review_notes'] = $request['status'] === 'approved'
                    ? 'Approved by administration.'
                    : 'Unable to approve due to staffing coverage.';
            }

            DB::table('leave_requests')->updateOrInsert(
                ['user_id' => $user->id, 'start_date' => $start, 'end_date' => $end],
                $attributes,
            );
        }
    }

    // ----------------------------------------------------------------------
    // Announcements & read receipts
    // ----------------------------------------------------------------------

    private function seedAnnouncements(): void
    {
        $admin = User::where('username', 'admin')->first();
        $today = Carbon::today();

        $announcements = [
            [
                'title' => 'Clinic Holiday Schedule — November',
                'body' => 'Independence Day falls on 9 November. Outpatient services will operate on a reduced roster; the emergency line remains available 24 hours.',
                'audience' => 'all', 'priority' => 'high', 'category' => 'general',
                'published' => true, 'published_at' => $today->copy()->subDays(2), 'expires_at' => $today->copy()->addDays(30),
            ],
            [
                'title' => 'New Diagnostic Laboratory Now Open',
                'body' => 'Our on-site laboratory is now fully operational, offering same-day blood tests, urinalysis and lipid profiles.',
                'audience' => 'all', 'priority' => 'normal', 'category' => 'event',
                'published' => true, 'published_at' => $today->copy()->subDays(5), 'expires_at' => null,
            ],
            [
                'title' => 'Hand Hygiene Protocol Reminder',
                'body' => 'All clinical staff must follow the five-moment hand hygiene protocol before and after every patient contact.',
                'audience' => 'staff', 'priority' => 'normal', 'category' => 'health',
                'published' => true, 'published_at' => $today->copy()->subDays(7), 'expires_at' => null,
            ],
            [
                'title' => 'Q4 Staff Training Schedule',
                'body' => 'Mandatory training sessions for Q4 will be held every Friday afternoon. Please check the schedule with your department head.',
                'audience' => 'staff', 'priority' => 'high', 'category' => 'hr',
                'published' => true, 'published_at' => $today->copy()->subDays(3), 'expires_at' => null,
            ],
            [
                'title' => 'Scheduled System Maintenance',
                'body' => 'The patient management system will be unavailable on Sunday from 02:00 to 04:00 for scheduled maintenance.',
                'audience' => 'all', 'priority' => 'normal', 'category' => 'maintenance',
                'published' => true, 'published_at' => $today->copy()->subDay(), 'expires_at' => $today->copy()->addDays(2),
            ],
            [
                'title' => 'Flu Vaccination Campaign',
                'body' => 'Annual influenza vaccination will be offered to all staff and registered patients. Dates to be confirmed.',
                'audience' => 'all', 'priority' => 'normal', 'category' => 'health',
                'published' => false, 'published_at' => null, 'expires_at' => null,
            ],
            [
                'title' => 'Annual Health Fair 2027',
                'body' => 'Save the date for our community health fair, featuring free screenings and health education booths.',
                'audience' => 'all', 'priority' => 'normal', 'category' => 'event',
                'published' => false, 'published_at' => $today->copy()->addDays(14), 'expires_at' => null,
            ],
            [
                'title' => 'Old Parking Policy',
                'body' => 'This policy has been superseded by the updated visitor parking guidelines issued last month.',
                'audience' => 'staff', 'priority' => 'normal', 'category' => 'policy',
                'published' => true, 'published_at' => $today->copy()->subDays(60), 'expires_at' => $today->copy()->subDays(30),
            ],
            [
                'title' => 'COVID-19 Response Procedures',
                'body' => 'Archived procedures from the pandemic response period, retained for historical reference only.',
                'audience' => 'all', 'priority' => 'normal', 'category' => 'health',
                'published' => true, 'published_at' => $today->copy()->subDays(120), 'expires_at' => null, 'archived_at' => $today->copy()->subDays(90),
            ],
        ];

        foreach ($announcements as $definition) {
            $announcement = Announcement::updateOrCreate(
                ['title' => $definition['title']],
                [
                    'body' => $definition['body'],
                    'audience' => $definition['audience'],
                    'priority' => $definition['priority'],
                    'category' => $definition['category'],
                    'is_published' => $definition['published'],
                    'published_at' => $definition['published_at'],
                    'expires_at' => $definition['expires_at'],
                    'archived_at' => $definition['archived_at'] ?? null,
                    'created_by' => $admin?->id,
                ],
            );

            // Read receipts for published announcements.
            if (! $definition['published'] || ($definition['archived_at'] ?? null)) {
                continue;
            }

            $readers = User::whereIn('username', ['doctor', 'nurse', 'headnurse', 'receptionist', 'pharmacist'])
                ->limit(3 + ($announcement->id % 3))
                ->get();

            foreach ($readers as $reader) {
                AnnouncementRead::updateOrCreate(
                    ['announcement_id' => $announcement->id, 'user_id' => $reader->id],
                    ['read_at' => $definition['published_at']->copy()->addHours(2)],
                );
            }
        }
    }

    // ----------------------------------------------------------------------
    // Notifications (database channel)
    // ----------------------------------------------------------------------

    private function seedNotifications(): void
    {
        // Remove only demo-generated notifications so re-seeding is stable.
        DB::table('notifications')
            ->where('data', 'like', '%"demo":true%')
            ->delete();

        $users = User::whereIn('username', ['doctor', 'nurse', 'headnurse', 'receptionist', 'pharmacist', 'admin'])
            ->get()
            ->keyBy('username');

        $doctor = $users['doctor'] ?? null;
        $nurse = $users['nurse'] ?? null;
        $headnurse = $users['headnurse'] ?? null;
        $receptionist = $users['receptionist'] ?? null;
        $pharmacist = $users['pharmacist'] ?? null;

        $payloads = [];

        if ($doctor !== null) {
            $payloads[] = [$doctor, 'App\Notifications\SystemAnnouncementNotification', [
                'kind' => 'system_announcement', 'title' => 'Clinic Holiday Schedule — November',
                'body' => 'Independence Day falls on 9 November. Outpatient services will operate on a reduced roster.',
                'url' => '/news', 'announcement_id' => 1, 'priority' => 'high', 'demo' => true,
            ], true];
            $payloads[] = [$doctor, 'App\Notifications\NursingTaskAssignedNotification', [
                'kind' => 'nursing_task_assigned', 'task_id' => 1, 'patient_id' => 1,
                'patient_name' => 'សុខ ចាន់ថា', 'title' => 'Record four-hourly vital signs',
                'task_type' => 'vital_signs', 'priority' => 'normal',
                'scheduled_at' => now()->subHours(2)->toIso8601String(), 'url' => '/patients/1', 'demo' => true,
            ], false];
        }

        if ($nurse !== null) {
            $payloads[] = [$nurse, 'App\Notifications\PatientAssignedNotification', [
                'kind' => 'patient_assigned', 'assignment_id' => 1, 'patient_id' => 1,
                'patient_name' => 'សុខ ចាន់ថា', 'ward_name' => 'General Medicine Ward',
                'shift' => 'day', 'url' => '/patients/1', 'demo' => true,
            ], false];
            $payloads[] = [$nurse, 'App\Notifications\NursingTaskOverdueNotification', [
                'kind' => 'nursing_task_overdue', 'task_id' => 2, 'patient_id' => 2,
                'patient_name' => 'ចន់ សុភា', 'title' => 'Administer scheduled medication',
                'task_type' => 'medication', 'priority' => 'high',
                'scheduled_at' => now()->subHours(4)->toIso8601String(),
                'due_at' => now()->subHours(3)->toIso8601String(),
                'minutes_overdue' => 60, 'url' => '/patients/2', 'demo' => true,
            ], false];
        }

        if ($headnurse !== null) {
            $payloads[] = [$headnurse, 'App\Notifications\SystemAnnouncementNotification', [
                'kind' => 'system_announcement', 'title' => 'Q4 Staff Training Schedule',
                'body' => 'Mandatory training sessions for Q4 will be held every Friday afternoon.',
                'url' => '/news', 'announcement_id' => 4, 'priority' => 'high', 'demo' => true,
            ], true];
        }

        if ($receptionist !== null) {
            $payloads[] = [$receptionist, 'App\Notifications\NewMessageNotification', [
                'kind' => 'new_message', 'conversation_id' => 1,
                'conversation_title' => 'Clinical Team', 'message_id' => 1,
                'sender_name' => 'សុខ ចាន់ថា', 'preview' => 'Please review the new lab results.',
                'is_group' => true, 'url' => '/chat/1', 'demo' => true,
            ], false];
        }

        if ($pharmacist !== null) {
            $payloads[] = [$pharmacist, 'App\Notifications\ContactMessageReceivedNotification', [
                'kind' => 'contact_message', 'contact_message_id' => 1,
                'sender_name' => 'John Doe', 'sender_email' => 'john@example.com',
                'subject' => 'Medication availability', 'preview' => 'Do you have amoxicillin in stock?',
                'url' => '/website/contact-messages', 'demo' => true,
            ], false];
        }

        foreach ($payloads as $index => [$user, $type, $data, $read]) {
            DB::table('notifications')->insert([
                'id' => Str::uuid()->toString(),
                'type' => $type,
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'data' => json_encode($data),
                'read_at' => $read ? now()->subHours(3) : null,
                'created_at' => now()->subHours(($index + 1) * 2),
                'updated_at' => now()->subHours(($index + 1) * 2),
            ]);
        }
    }

    // ----------------------------------------------------------------------
    // System alerts
    // ----------------------------------------------------------------------

    private function seedSystemAlerts(): void
    {
        // Link alerts to real patients where the schema requires it, so the
        // seeder works on any database (not just a freshly seeded one).
        $patients = Patient::query()->orderBy('id')->limit(2)->get();
        $firstPatientId = $patients->get(0)?->id;
        $secondPatientId = $patients->get(1)?->id;

        $alerts = [
            ['source' => 'demo-low-stock-001', 'category' => 'inventory', 'severity' => 'warning', 'title' => 'Low stock: Amoxicillin 500mg', 'message' => 'Current stock is below the reorder level.', 'status' => 'active', 'module' => 'pharmacy', 'url' => '/pharmacy/dashboard', 'patient_id' => null],
            ['source' => 'demo-expiring-001', 'category' => 'inventory', 'severity' => 'info', 'title' => 'Batch expiring within 30 days', 'message' => 'One paracetamol batch expires soon.', 'status' => 'acknowledged', 'module' => 'pharmacy', 'url' => '/inventory', 'patient_id' => null],
            ['source' => 'demo-billing-001', 'category' => 'inpatient_billing', 'severity' => 'warning', 'title' => 'Unbilled charges above threshold', 'message' => 'Total unbilled inpatient charges have exceeded $500.', 'status' => 'active', 'module' => 'billing', 'url' => '/admissions', 'patient_id' => $firstPatientId],
            ['source' => 'demo-appointments-001', 'category' => 'appointments', 'severity' => 'info', 'title' => 'Unconfirmed appointments today', 'message' => '3 appointments are still awaiting confirmation.', 'status' => 'active', 'module' => 'appointments', 'url' => '/appointments', 'patient_id' => null],
            ['source' => 'demo-staff-001', 'category' => 'staff', 'severity' => 'warning', 'title' => 'Professional licences expiring', 'message' => '2 staff licences expire within 60 days.', 'status' => 'active', 'module' => 'staff', 'url' => '/staff', 'patient_id' => null],
            ['source' => 'demo-attendance-001', 'category' => 'attendance', 'severity' => 'critical', 'title' => 'Missing clock-out detected', 'message' => 'An employee has not recorded a clock-out for the previous shift.', 'status' => 'active', 'module' => 'attendance', 'url' => '/attendance', 'patient_id' => null],
            ['source' => 'demo-clinical-001', 'category' => 'clinical', 'severity' => 'critical', 'title' => 'Overdue nursing task', 'message' => 'A high-priority medication task is overdue by 60 minutes.', 'status' => 'acknowledged', 'module' => 'nursing', 'url' => '/nursing-tasks', 'patient_id' => $secondPatientId],
            ['source' => 'demo-system-001', 'category' => 'system', 'severity' => 'info', 'title' => 'Nightly backup completed', 'message' => 'The database backup completed successfully.', 'status' => 'resolved', 'module' => 'system', 'url' => null, 'patient_id' => null],
            ['source' => 'demo-inventory-002', 'category' => 'inventory', 'severity' => 'critical', 'title' => 'Out of stock: Salbutamol Inhaler', 'message' => 'No usable stock is available for this medicine.', 'status' => 'active', 'module' => 'pharmacy', 'url' => '/pharmacy/dashboard', 'patient_id' => null],
            ['source' => 'demo-appointments-002', 'category' => 'appointments', 'severity' => 'warning', 'title' => 'No-shows this week', 'message' => '4 patients did not attend scheduled appointments.', 'status' => 'active', 'module' => 'appointments', 'url' => '/appointments', 'patient_id' => null],
        ];

        $acknowledgedBy = User::where('username', 'headnurse')->value('id');
        $resolvedBy = User::where('username', 'admin')->value('id');

        foreach ($alerts as $alert) {
            $attributes = [
                'category' => $alert['category'],
                'severity' => $alert['severity'],
                'title' => $alert['title'],
                'message' => $alert['message'],
                'module' => $alert['module'],
                'url' => $alert['url'],
                'data' => json_encode(['demo' => true]),
                'status' => $alert['status'],
                'patient_id' => $alert['patient_id'],
                'read_at' => $alert['status'] === 'active' ? null : now()->subHours(2),
                'acknowledged_by' => $alert['status'] === 'acknowledged' ? $acknowledgedBy : null,
                'acknowledged_at' => $alert['status'] === 'acknowledged' ? now()->subHours(2) : null,
                'resolved_by' => $alert['status'] === 'resolved' ? $resolvedBy : null,
                'resolved_at' => $alert['status'] === 'resolved' ? now()->subHours(5) : null,
            ];

            SystemAlert::updateOrCreate(['source_key' => $alert['source']], $attributes);
        }
    }

    // ----------------------------------------------------------------------
    // Activity / audit log
    // ----------------------------------------------------------------------

    private function seedActivityLogs(): void
    {
        DB::table('activity_logs')->where('properties', 'like', '%"demo":true%')->delete();

        $users = User::whereIn('username', ['admin', 'doctor', 'nurse', 'receptionist', 'pharmacist'])
            ->get()
            ->keyBy('username');

        $entries = [
            ['admin', 'login', 'Signed in to the admin dashboard.', User::class, 1, '127.0.0.1'],
            ['doctor', 'created', 'Created a new appointment.', Appointment::class, 1, '127.0.0.1'],
            ['doctor', 'updated', 'Updated a consultation record.', Appointment::class, 1, '127.0.0.1'],
            ['nurse', 'created', 'Recorded vital signs for a patient.', Patient::class, 1, '127.0.0.1'],
            ['receptionist', 'created', 'Registered a new patient.', Patient::class, 2, '127.0.0.1'],
            ['receptionist', 'updated', 'Updated patient contact details.', Patient::class, 2, '127.0.0.1'],
            ['pharmacist', 'created', 'Dispensed a prescription.', Patient::class, 1, '127.0.0.1'],
            ['doctor', 'created', 'Issued a prescription.', Patient::class, 1, '127.0.0.1'],
            ['admin', 'created', 'Created a user account.', User::class, 2, '127.0.0.1'],
            ['admin', 'updated', 'Updated role permissions.', User::class, 2, '127.0.0.1'],
            ['doctor', 'login', 'Signed in to the clinic portal.', User::class, 1, '127.0.0.1'],
            ['nurse', 'login', 'Signed in to the clinic portal.', User::class, 1, '127.0.0.1'],
            ['receptionist', 'login', 'Signed in to the clinic portal.', User::class, 1, '127.0.0.1'],
            ['admin', 'login_failed', 'Failed sign-in attempt (invalid password).', User::class, 1, '127.0.0.1'],
            ['pharmacist', 'login', 'Signed in to the pharmacy module.', User::class, 1, '127.0.0.1'],
            ['doctor', 'updated', 'Updated a medical record.', Patient::class, 1, '127.0.0.1'],
            ['nurse', 'created', 'Created a nursing task.', Patient::class, 2, '127.0.0.1'],
            ['receptionist', 'created', 'Generated an invoice.', Invoice::class, 1, '127.0.0.1'],
            ['admin', 'created', 'Published an announcement.', Announcement::class, 1, '127.0.0.1'],
            ['doctor', 'deleted', 'Deleted a draft appointment.', Appointment::class, 2, '127.0.0.1'],
            ['admin', 'created', 'Uploaded a website media asset.', MediaAsset::class, 1, '127.0.0.1'],
            ['admin', 'updated', 'Updated website settings.', User::class, 1, '127.0.0.1'],
            ['nurse', 'updated', 'Completed a nursing task.', Patient::class, 1, '127.0.0.1'],
            ['pharmacist', 'updated', 'Adjusted medicine stock.', Medicine::class, 1, '127.0.0.1'],
            ['doctor', 'created', 'Admitted a patient.', Admission::class, 1, '127.0.0.1'],
            ['receptionist', 'created', 'Checked in a patient.', Patient::class, 3, '127.0.0.1'],
            ['admin', 'created', 'Created a shift template.', ShiftTemplate::class, 1, '127.0.0.1'],
            ['nurse', 'login', 'Signed in to the nursing station.', User::class, 1, '127.0.0.1'],
            ['doctor', 'logout', 'Signed out.', User::class, 1, '127.0.0.1'],
            ['admin', 'login', 'Signed in from a new device.', User::class, 1, '127.0.0.1'],
        ];

        foreach ($entries as $index => $entry) {
            [$username, $event, $description, $subjectType, $subjectId, $ip] = $entry;

            $user = $users[$username] ?? null;

            DB::table('activity_logs')->insert([
                'user_id' => $user?->id,
                'user_name' => $user?->name,
                'user_role' => $user?->getRoleNames()->first(),
                'event' => $event,
                'description' => $description,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'properties' => json_encode(['demo' => true, 'subject_label' => class_basename($subjectType).' #'.$subjectId]),
                'ip_address' => $ip,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'created_at' => now()->subHours(($index + 1) * 3),
            ]);
        }
    }

    // ----------------------------------------------------------------------
    // Team chat
    // ----------------------------------------------------------------------

    private function seedConversations(): void
    {
        $users = User::whereIn('username', ['doctor', 'doctor2', 'nurse', 'nurse2', 'headnurse', 'pharmacist', 'receptionist'])
            ->get()
            ->keyBy('username');

        if ($users->isEmpty()) {
            return;
        }

        $definitions = [
            [
                'type' => Conversation::TYPE_GROUP,
                'title' => 'Clinical Team',
                'members' => ['doctor', 'doctor2', 'headnurse', 'nurse', 'pharmacist'],
                'messages' => [
                    ['doctor', 'Please review the new lab results before the afternoon rounds.', -26],
                    ['headnurse', 'Received. I will hand over to the evening nurse.', -25],
                    ['pharmacist', 'Amoxicillin stock is running low; a purchase order has been raised.', -20],
                    ['doctor2', 'The cardiology follow-up list has been updated.', -6],
                    ['nurse', 'Vital signs for bed 3 are stable.', -2],
                ],
            ],
            [
                'type' => Conversation::TYPE_GROUP,
                'title' => 'Nursing Handover',
                'members' => ['headnurse', 'nurse', 'nurse2'],
                'messages' => [
                    ['headnurse', 'Handover sheet for the night shift is ready.', -10],
                    ['nurse2', 'Patient in bed 5 needs a dressing change at 22:00.', -9],
                    ['nurse', 'Noted, I will document it in the MAR.', -8],
                ],
            ],
            [
                'type' => Conversation::TYPE_PRIVATE,
                // Private conversations derive their display title from the
                // other participant, so a stable key here is safe and keeps
                // the seeder idempotent.
                'title' => 'private:doctor:nurse',
                'members' => ['doctor', 'nurse'],
                'messages' => [
                    ['doctor', 'Could you recheck the blood pressure for bed 2?', -14],
                    ['nurse', 'Done — 132/84, within target.', -13],
                    ['doctor', 'Thank you.', -13],
                ],
            ],
            [
                'type' => Conversation::TYPE_PRIVATE,
                'title' => 'private:receptionist:doctor',
                'members' => ['receptionist', 'doctor'],
                'messages' => [
                    ['receptionist', 'Your 15:00 patient has arrived.', -5],
                    ['doctor', 'I will be five minutes late; please ask them to wait.', -4],
                ],
            ],
        ];

        foreach ($definitions as $definition) {
            $existing = Conversation::query()
                ->where('type', $definition['type'])
                ->where('title', $definition['title'])
                ->first();

            if ($existing !== null) {
                continue;
            }

            $conversation = Conversation::create([
                'type' => $definition['type'],
                'title' => $definition['title'],
                'created_by' => $users[$definition['members'][0]]->id,
            ]);

            foreach ($definition['members'] as $username) {
                if (! isset($users[$username])) {
                    continue;
                }

                ConversationParticipant::create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $users[$username]->id,
                    'joined_at' => now()->subDays(3),
                ]);
            }

            foreach ($definition['messages'] as [$username, $body, $hoursAgo]) {
                if (! isset($users[$username])) {
                    continue;
                }

                $message = ConversationMessage::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $users[$username]->id,
                    'body' => $body,
                    'source' => 'web',
                ]);

                $message->forceFill([
                    'created_at' => now()->subHours(abs($hoursAgo)),
                    'updated_at' => now()->subHours(abs($hoursAgo)),
                ])->save();

                // Everyone except the sender has read the message.
                foreach ($definition['members'] as $member) {
                    if ($member === $username || ! isset($users[$member])) {
                        continue;
                    }

                    ConversationMessageRead::create([
                        'message_id' => $message->id,
                        'user_id' => $users[$member]->id,
                        'read_at' => now()->subHours(abs($hoursAgo) - 1),
                    ]);
                }
            }
        }
    }

    // ----------------------------------------------------------------------
    // Expert system audit trail
    // ----------------------------------------------------------------------

    private function seedExpertAudits(): void
    {
        if (ExpertEvaluationAudit::query()->where('engine_version', 'like', 'demo-%')->exists()) {
            return;
        }

        $symptomSets = [
            ['SYM-FEVER', 'SYM-COUGH', 'SYM-SORE-THROAT'],
            ['SYM-HEADACHE', 'SYM-PHOTOPHOBIA', 'SYM-NAUSEA'],
            ['SYM-ABDOMINAL-PAIN', 'SYM-DIARRHEA', 'SYM-VOMITING'],
            ['SYM-SHORTNESS-OF-BREATH', 'SYM-WHEEZING', 'SYM-CHEST-TIGHTNESS'],
            ['SYM-DIZZINESS', 'SYM-PALPITATIONS', 'SYM-CHEST-PAIN-BREATHING'],
            ['SYM-FREQUENT-URINATION', 'SYM-BURNING-URINATION', 'SYM-URGENCY'],
            ['SYM-RASH', 'SYM-ITCHY-EYES', 'SYM-SNEEZING'],
            ['SYM-BODY-ACHE', 'SYM-FATIGUE', 'SYM-CHILLS'],
        ];

        $ruleSets = [
            ['RULE-COMMON-COLD'],
            ['RULE-MIGRAINE', 'RULE-TENSION-HEADACHE'],
            ['RULE-GASTROENTERITIS'],
            ['RULE-ASTHMA'],
            ['RULE-HYPERTENSION'],
            ['RULE-UTI'],
            ['RULE-ALLERGIC-RHINITIS'],
            ['RULE-INFLUENZA', 'RULE-DENGUE'],
        ];

        for ($i = 0; $i < 20; $i++) {
            $symptoms = $symptomSets[$i % count($symptomSets)];
            $rules = $ruleSets[$i % count($ruleSets)];

            ExpertEvaluationAudit::create([
                'symptom_ids' => $symptoms,
                'matched_rule_count' => count($rules),
                'matched_rule_codes' => $rules,
                'engine_version' => 'demo-'.($i + 1),
                'is_clinically_validated' => $i % 3 === 0,
                'created_at' => now()->subDays($i % 14),
                'updated_at' => now()->subDays($i % 14),
            ]);
        }
    }

    // ----------------------------------------------------------------------
    // Staff invitations
    // ----------------------------------------------------------------------

    private function seedStaffInvitations(): void
    {
        $admin = User::where('username', 'admin')->first();
        $today = Carbon::today();

        $invitations = [
            ['email' => 'doctor@clinic.local', 'name' => 'សុខ ចាន់ថា', 'role' => 'Doctor', 'staff' => 'STF-0001', 'accepted' => $today->copy()->subYears(8), 'revoked' => null, 'expires' => null],
            ['email' => 'nurse@clinic.local', 'name' => 'ចន់ សុភា', 'role' => 'Nurse', 'staff' => 'STF-0003', 'accepted' => $today->copy()->subYears(6), 'revoked' => null, 'expires' => null],
            ['email' => 'dr.nimol@clinic.local', 'name' => 'និមល សុខា', 'role' => 'Doctor', 'staff' => 'STF-0014', 'accepted' => $today->copy()->subDays(2), 'revoked' => null, 'expires' => null],
            ['email' => 'nurse.dalin@clinic.local', 'name' => 'ដាលីន ចន្ថា', 'role' => 'Nurse', 'staff' => 'STF-0017', 'accepted' => $today->copy()->subDay(), 'revoked' => null, 'expires' => null],
            ['email' => 'dr.sopheap@clinic.local', 'name' => 'សុភាល សុខា', 'role' => 'Doctor', 'staff' => null, 'accepted' => null, 'revoked' => null, 'expires' => $today->copy()->addDays(7)],
            ['email' => 'sreypov.h@clinic.local', 'name' => 'ស្រីពៅ ហេង', 'role' => 'Nurse', 'staff' => null, 'accepted' => null, 'revoked' => $today->copy()->subDays(3), 'expires' => $today->copy()->addDays(4)],
            ['email' => 'old.invite@clinic.local', 'name' => 'បុគ្គលិកចាស់', 'role' => 'Receptionist', 'staff' => null, 'accepted' => null, 'revoked' => null, 'expires' => $today->copy()->subDays(5)],
        ];

        foreach ($invitations as $index => $invitation) {
            $staff = $invitation['staff'] !== null
                ? DB::table('staff')->where('staff_number', $invitation['staff'])->value('id')
                : null;

            $exists = StaffInvitation::query()
                ->where('email', $invitation['email'])
                ->where('name', $invitation['name'])
                ->exists();

            if ($exists) {
                continue;
            }

            StaffInvitation::create([
                'staff_id' => $staff,
                'user_id' => null,
                'invited_by' => $admin?->id,
                'email' => $invitation['email'],
                'name' => $invitation['name'],
                'role' => $invitation['role'],
                'token_hash' => hash('sha256', 'demo-invite-'.$index.'-'.Str::uuid()->toString()),
                'expires_at' => $invitation['expires'],
                'accepted_at' => $invitation['accepted'],
                'revoked_at' => $invitation['revoked'],
            ]);
        }
    }

    // ----------------------------------------------------------------------
    // Website contact messages
    // ----------------------------------------------------------------------

    private function seedContactMessages(): void
    {
        if (ContactMessage::query()->exists()) {
            return;
        }

        $messages = [
            ['name' => 'John Doe', 'email' => 'john@example.com', 'phone' => '012345678', 'subject' => 'Medication availability', 'message' => 'Do you have amoxicillin 500mg in stock? I need it for my prescription.', 'read' => true],
            ['name' => 'Srey Pov', 'email' => 'sreypov@example.com', 'phone' => '098765432', 'subject' => 'Opening hours', 'message' => 'Are you open on public holidays? I would like to book a check-up.', 'read' => true],
            ['name' => 'Michael Chen', 'email' => 'mchen@example.com', 'phone' => null, 'subject' => 'Insurance claim', 'message' => 'Can you provide an itemised invoice for my recent visit? My insurer requires it.', 'read' => false],
            ['name' => 'ចន្ថា សុភា', 'email' => 'chantha@example.com', 'phone' => '011222333', 'subject' => 'Lab results', 'message' => 'When will my blood test results be ready? I visited last week.', 'read' => false],
            ['name' => 'David Kim', 'email' => 'dkim@example.com', 'phone' => '099888777', 'subject' => 'Appointment rescheduling', 'message' => 'I need to reschedule my appointment from Friday to Monday.', 'read' => true],
            ['name' => 'Sokha Meas', 'email' => 'sokha@example.com', 'phone' => null, 'subject' => 'Vaccination', 'message' => 'Do you offer influenza vaccination for children under five?', 'read' => false],
            ['name' => 'Robert Smith', 'email' => 'rsmith@example.com', 'phone' => '097654321', 'subject' => 'Billing enquiry', 'message' => 'I received an invoice but I believe the amount is incorrect. Please advise.', 'read' => false],
            ['name' => 'ដារា លី', 'email' => 'dara@example.com', 'phone' => '096111222', 'subject' => 'Thank you', 'message' => 'Thank you to the team for the excellent care during my recent admission.', 'read' => true],
        ];

        foreach ($messages as $index => $message) {
            ContactMessage::create([
                'name' => $message['name'],
                'email' => $message['email'],
                'phone' => $message['phone'],
                'subject' => $message['subject'],
                'message' => $message['message'],
                'read_at' => $message['read'] ? now()->subDays($index + 1) : null,
                'created_at' => now()->subDays($index + 1),
                'updated_at' => now()->subDays($index + 1),
            ]);
        }
    }

    // ----------------------------------------------------------------------
    // Website media library
    // ----------------------------------------------------------------------

    private function seedMediaAssets(): void
    {
        if (MediaAsset::query()->exists()) {
            return;
        }

        $adminId = User::where('username', 'admin')->value('id');

        $images = [
            ['name' => 'Modern reception and waiting area', 'seed' => 'reception'],
            ['name' => 'Private consultation room', 'seed' => 'consultation'],
            ['name' => 'On-site diagnostic laboratory', 'seed' => 'laboratory'],
            ['name' => 'Cardiology and ECG suite', 'seed' => 'cardiology'],
            ['name' => 'Comfortable patient rooms', 'seed' => 'patient-room'],
            ['name' => 'Community health day', 'seed' => 'health-day'],
            ['name' => 'Pharmacy dispensary', 'seed' => 'pharmacy'],
            ['name' => 'Nursing station', 'seed' => 'nursing-station'],
            ['name' => 'Waiting area seating', 'seed' => 'waiting-area'],
            ['name' => 'Medical equipment', 'seed' => 'equipment'],
            ['name' => 'Clinic exterior', 'seed' => 'exterior'],
            ['name' => 'Staff at work', 'seed' => 'staff'],
        ];

        foreach ($images as $index => $image) {
            MediaAsset::create([
                'name' => $image['name'],
                'file_path' => 'https://picsum.photos/seed/royalcare-'.$image['seed'].'/800/600',
                'public_id' => null,
                'resource_type' => 'image',
                'disk' => 'public',
                'mime_type' => 'image/jpeg',
                'size' => 120000 + ($index * 8000),
                'width' => 800,
                'height' => 600,
                'uploaded_by' => $adminId,
            ]);
        }

        foreach ([
            ['name' => 'Clinic tour', 'seed' => 'tour'],
            ['name' => 'Patient education', 'seed' => 'education'],
        ] as $index => $video) {
            MediaAsset::create([
                'name' => $video['name'],
                'file_path' => 'https://picsum.photos/seed/royalcare-'.$video['seed'].'/800/600',
                'public_id' => null,
                'resource_type' => 'video',
                'disk' => 'public',
                'mime_type' => 'video/mp4',
                'size' => 2400000 + ($index * 100000),
                'width' => 800,
                'height' => 600,
                'uploaded_by' => $adminId,
            ]);
        }
    }

    // ----------------------------------------------------------------------
    // Patient documents
    // ----------------------------------------------------------------------

    private function seedPatientDocuments(): void
    {
        if (PatientDocument::query()->exists()) {
            return;
        }

        $uploaderId = User::where('username', 'doctor')->value('id');
        $patients = Patient::query()->orderBy('id')->take(8)->get();

        $categories = ['lab-result', 'imaging', 'referral', 'consent', 'insurance', 'other'];

        foreach ($patients as $patientIndex => $patient) {
            $documents = [
                ['title' => 'Laboratory Report', 'category' => 'lab-result', 'name' => 'Lab-Report.pdf'],
                ['title' => 'Chest X-Ray', 'category' => 'imaging', 'name' => 'Chest-XRay.pdf'],
            ];

            if ($patientIndex % 2 === 0) {
                $documents[] = ['title' => 'Referral Letter', 'category' => 'referral', 'name' => 'Referral-Letter.pdf'];
            }

            foreach ($documents as $document) {
                $path = 'patient-documents/'.$patient->id.'/'.Str::uuid()->toString().'.pdf';
                Storage::disk('local')->put($path, $this->makePdf($document['title']));

                PatientDocument::create([
                    'patient_id' => $patient->id,
                    'uploaded_by' => $uploaderId,
                    'title' => $document['title'],
                    'category' => $document['category'],
                    'original_name' => $document['name'],
                    'path' => $path,
                    'mime_type' => 'application/pdf',
                    'size' => 2048,
                ]);
            }
        }
    }

    // ----------------------------------------------------------------------
    // Inventory: expiring, expired and low-stock batches
    // ----------------------------------------------------------------------

    private function seedInventoryDemo(): void
    {
        $medicines = Medicine::query()->orderBy('id')->get();

        if ($medicines->isEmpty()) {
            return;
        }

        $pharmacist = User::where('username', 'pharmacist')->first();

        // A batch expiring within 30 days.
        $expiring = $medicines->first();
        $expiringBatch = 'BATCH-EXPIRING-'.now()->format('Ymd');

        $expiringItem = InventoryItem::updateOrCreate(
            ['medicine_id' => $expiring->id, 'batch_number' => $expiringBatch],
            [
                'quantity' => 40,
                'expiration_date' => now()->addDays(20)->toDateString(),
                'received_date' => now()->subDays(60)->toDateString(),
                'location' => 'ធ្នើ A1',
            ],
        );

        StockMovement::updateOrCreate(
            ['reference' => $expiringBatch, 'medicine_id' => $expiring->id],
            [
                'inventory_item_id' => $expiringItem->id,
                'user_id' => $pharmacist?->id,
                'type' => 'in',
                'quantity' => 40,
                'reason' => 'Stock receipt',
            ],
        );

        // An expired batch that has not been disposed of yet.
        $expired = $medicines->skip(1)->first();
        $expiredBatch = 'BATCH-EXPIRED-'.now()->format('Ymd');

        $expiredItem = InventoryItem::updateOrCreate(
            ['medicine_id' => $expired->id, 'batch_number' => $expiredBatch],
            [
                'quantity' => 8,
                'expiration_date' => now()->subDays(5)->toDateString(),
                'received_date' => now()->subYear()->toDateString(),
                'location' => 'ធ្នើ B1',
            ],
        );

        StockMovement::updateOrCreate(
            ['reference' => $expiredBatch, 'medicine_id' => $expired->id],
            [
                'inventory_item_id' => $expiredItem->id,
                'user_id' => $pharmacist?->id,
                'type' => 'in',
                'quantity' => 8,
                'reason' => 'Stock receipt',
            ],
        );

        // Add a new medicine that is below its reorder level so the low-stock
        // list is populated on the pharmacy dashboard and reports. Adding a
        // catalogue row (rather than rewriting an existing batch) keeps real
        // stock quantities untouched.
        $lowStock = Medicine::updateOrCreate(
            ['code' => 'MED-0013'],
            [
                'name' => 'Cough Relief Syrup',
                'generic_name' => 'Dextromethorphan',
                'category' => 'Cough & Cold',
                'form' => 'Syrup',
                'strength' => '15 mg/5ml',
                'unit' => 'bottle',
                'manufacturer' => 'RoyalCare Pharmacy',
                'description' => 'Symptomatic relief of dry cough in adults and children over 6 years.',
                'price' => 3.50,
                'reorder_level' => 20,
                'is_active' => true,
                'requires_prescription' => false,
                'is_recalled' => false,
            ],
        );

        $lowItem = InventoryItem::updateOrCreate(
            ['medicine_id' => $lowStock->id, 'batch_number' => 'BATCH-LOW-'.now()->format('Ymd')],
            [
                'quantity' => 3,
                'expiration_date' => now()->addMonths(8)->toDateString(),
                'received_date' => now()->subMonths(2)->toDateString(),
                'location' => 'ធ្នើ A2',
            ],
        );

        StockMovement::updateOrCreate(
            ['reference' => 'BATCH-LOW-'.now()->format('Ymd'), 'medicine_id' => $lowStock->id],
            [
                'inventory_item_id' => $lowItem->id,
                'user_id' => $pharmacist?->id,
                'type' => 'in',
                'quantity' => 3,
                'reason' => 'Stock receipt',
            ],
        );

        // A couple of today's movements so the pharmacy dashboard counters move.
        foreach ([['in', 25], ['out', 6], ['adjustment', 2]] as $index => [$type, $quantity]) {
            $medicine = $medicines->get($index % $medicines->count());
            $item = $medicine->inventoryItems()->first();

            if ($item === null) {
                continue;
            }

            StockMovement::updateOrCreate(
                [
                    'reference' => 'DEMO-'.now()->format('Ymd').'-'.$type.'-'.$medicine->id,
                    'medicine_id' => $medicine->id,
                ],
                [
                    'inventory_item_id' => $item->id,
                    'user_id' => $pharmacist?->id,
                    'type' => $type,
                    'quantity' => $quantity,
                    'reason' => 'Daily stock movement (demo).',
                ],
            );
        }
    }

    // ----------------------------------------------------------------------
    // Pharmacy POS sales
    // ----------------------------------------------------------------------

    private function seedPharmacySales(): void
    {
        $pharmacist = User::where('username', 'pharmacist')->first();
        $inventory = app(InventoryService::class);

        $medicines = Medicine::query()
            ->where('is_active', true)
            ->where('requires_prescription', false)
            ->where(fn ($query) => $query->whereNull('is_recalled')->orWhere('is_recalled', false))
            ->orderBy('id')
            ->take(8)
            ->get();

        if ($pharmacist === null || $medicines->isEmpty()) {
            return;
        }

        $sales = [
            ['medicine' => 0, 'quantity' => 2, 'payment' => true],
            ['medicine' => 1, 'quantity' => 1, 'payment' => true],
            ['medicine' => 3, 'quantity' => 3, 'payment' => true],
            ['medicine' => 4, 'quantity' => 1, 'payment' => false],
            ['medicine' => 8, 'quantity' => 2, 'payment' => true],
            ['medicine' => 9, 'quantity' => 1, 'payment' => true],
            ['medicine' => 11, 'quantity' => 2, 'payment' => true],
            ['medicine' => 2, 'quantity' => 1, 'payment' => false],
            ['medicine' => 5, 'quantity' => 2, 'payment' => true],
            ['medicine' => 10, 'quantity' => 1, 'payment' => true],
        ];

        foreach ($sales as $index => $sale) {
            $medicine = $medicines[$sale['medicine'] % $medicines->count()];
            $number = 'POS-2026-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT);

            if (PharmacySale::query()->where('sale_number', $number)->exists()) {
                continue;
            }

            $quantity = $sale['quantity'];
            $subtotal = round((float) $medicine->price * $quantity, 2);
            $daysAgo = $index + 1;

            try {
                $result = DB::transaction(function () use ($medicine, $quantity, $subtotal, $number, $pharmacist, $inventory) {
                    $saleModel = PharmacySale::create([
                        'sale_number' => $number,
                        'idempotency_key' => (string) Str::uuid(),
                        'patient_id' => null,
                        'prescription_id' => null,
                        'invoice_id' => null,
                        'sold_by' => $pharmacist->id,
                        'status' => 'completed',
                        'subtotal' => $subtotal,
                        'discount' => 0,
                        'total' => $subtotal,
                    ]);

                    $invoice = Invoice::create([
                        'invoice_number' => 'INV-POS-'.str_pad((string) $saleModel->id, 4, '0', STR_PAD_LEFT),
                        'patient_id' => null,
                        'consultation_id' => null,
                        'admission_id' => null,
                        'status' => 'unpaid',
                        'invoice_type' => 'outpatient',
                        'subtotal' => $subtotal,
                        'discount' => 0,
                        'tax' => 0,
                        'total' => $subtotal,
                        'amount_paid' => 0,
                        'balance' => $subtotal,
                        'issued_at' => now()->subDays($saleModel->id),
                        'due_date' => now()->subDays($saleModel->id),
                        'notes' => 'Pharmacy POS demo sale.',
                        'created_by' => $pharmacist->id,
                    ]);

                    $saleModel->update(['invoice_id' => $invoice->id]);

                    $item = PharmacySaleItem::create([
                        'pharmacy_sale_id' => $saleModel->id,
                        'medicine_id' => $medicine->id,
                        'quantity' => $quantity,
                        'unit_price' => $medicine->price,
                        'discount' => 0,
                        'total' => $subtotal,
                    ]);

                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'medicine_id' => $medicine->id,
                        'description' => $medicine->name.' '.$medicine->strength,
                        'quantity' => $quantity,
                        'unit_price' => $medicine->price,
                        'amount' => $subtotal,
                    ]);

                    foreach ($inventory->allocate($medicine, $quantity, $pharmacist->id, 'out', 'POS sale', $number) as $allocation) {
                        $item->allocations()->create([
                            'inventory_item_id' => $allocation['batch']->id,
                            'stock_movement_id' => $allocation['movement']->id,
                            'quantity' => $allocation['quantity'],
                        ]);
                    }

                    return [$saleModel, $invoice];
                });
            } catch (\Throwable $e) {
                // Skip a sale when stock is insufficient rather than aborting.
                continue;
            }

            [$saleModel, $invoice] = $result;

            if ($sale['payment']) {
                Payment::create([
                    'invoice_id' => $invoice->id,
                    'amount' => $subtotal,
                    'method' => 'cash',
                    'reference' => 'PAY-POS-'.str_pad((string) $saleModel->id, 4, '0', STR_PAD_LEFT),
                    'idempotency_key' => (string) Str::uuid(),
                    'paid_at' => now()->subDays($daysAgo)->setTime(10, 30),
                    'received_by' => $pharmacist->id,
                    'notes' => 'Paid in full at the pharmacy counter.',
                ]);

                $invoice->update([
                    'amount_paid' => $subtotal,
                    'balance' => 0,
                    'status' => 'paid',
                ]);
            }

            $saleModel->forceFill(['created_at' => now()->subDays($daysAgo)])->save();
            $invoice->forceFill(['created_at' => now()->subDays($daysAgo)])->save();
        }
    }

    // ----------------------------------------------------------------------
    // Inpatient billing (charges + deposits)
    // ----------------------------------------------------------------------

    private function seedAdmissionBilling(): void
    {
        $doctor = User::where('username', 'doctor')->first();
        $receptionist = User::where('username', 'receptionist')->first();
        $services = BillableService::query()->get()->keyBy('code');
        $medicines = Medicine::query()->orderBy('id')->take(6)->get();

        $admissions = Admission::query()->where('status', Admission::STATUS_ADMITTED)->get();

        foreach ($admissions as $index => $admission) {
            // One nursing/service charge per active admission.
            $service = $services['CONS-SPEC'] ?? $services->first();
            $nursing = $services['NUR-CARE'] ?? null;

            $charges = [
                [
                    'category' => AdmissionCharge::CATEGORY_SERVICE,
                    'description' => 'Specialist consultation',
                    'service' => $service,
                    'medicine' => null,
                    'quantity' => 1,
                    'price' => $service?->default_price ?? 20.00,
                ],
                [
                    'category' => AdmissionCharge::CATEGORY_NURSING,
                    'description' => 'Nursing care (day shift)',
                    'service' => $nursing,
                    'medicine' => null,
                    'quantity' => 1,
                    'price' => $nursing?->default_price ?? 15.00,
                ],
            ];

            $medicine = $medicines[$index % $medicines->count()];
            $charges[] = [
                'category' => AdmissionCharge::CATEGORY_MEDICINE,
                'description' => $medicine->name.' '.$medicine->strength,
                'service' => null,
                'medicine' => $medicine,
                'quantity' => 2,
                'price' => (float) $medicine->price,
            ];

            foreach ($charges as $charge) {
                $total = round($charge['quantity'] * $charge['price'], 2);

                $exists = AdmissionCharge::query()
                    ->where('admission_id', $admission->id)
                    ->where('description', $charge['description'])
                    ->where('source_type', 'manual')
                    ->exists();

                if ($exists) {
                    continue;
                }

                AdmissionCharge::create([
                    'admission_id' => $admission->id,
                    'patient_id' => $admission->patient_id,
                    'charge_date' => now()->subDay()->toDateString(),
                    'charge_time' => '08:00:00',
                    'category' => $charge['category'],
                    'description' => $charge['description'],
                    'billable_service_id' => $charge['service']?->id,
                    'medicine_id' => $charge['medicine']?->id,
                    'source_type' => 'manual',
                    'source_id' => null,
                    'quantity' => $charge['quantity'],
                    'unit_price' => $charge['price'],
                    'discount_amount' => 0,
                    'subtotal' => $total,
                    'total' => $total,
                    'status' => AdmissionCharge::STATUS_UNBILLED,
                    'bed_id' => $admission->bed_id,
                    'ward_id' => $admission->ward_id,
                    'created_by' => $doctor?->id,
                ]);
            }

            // One deposit per active admission.
            $depositAmount = 500.00;

            AdmissionDeposit::firstOrCreate(
                ['admission_id' => $admission->id, 'reference' => 'DEP-ADM-'.str_pad((string) $admission->id, 4, '0', STR_PAD_LEFT)],
                [
                    'patient_id' => $admission->patient_id,
                    'amount' => $depositAmount,
                    'balance' => $depositAmount,
                    'type' => AdmissionDeposit::TYPE_DEPOSIT,
                    'method' => 'cash',
                    'reference' => 'DEP-ADM-'.str_pad((string) $admission->id, 4, '0', STR_PAD_LEFT),
                    'received_by' => $receptionist?->id,
                    'received_at' => $admission->admitted_at,
                    'notes' => 'Admission deposit.',
                    'status' => AdmissionDeposit::STATUS_AVAILABLE,
                ],
            );
        }
    }

    // ----------------------------------------------------------------------
    // Recent patients, appointments, invoices and payments
    // ----------------------------------------------------------------------

    private function seedRecentActivity(): void
    {
        $doctors = User::role('Doctor')->orderBy('id')->get();
        $receptionist = User::where('username', 'receptionist')->first();
        $today = Carbon::today();

        if ($doctors->isEmpty()) {
            return;
        }

        $newPatients = [
            ['number' => 'PAT-2026-0011', 'first' => 'សុខ', 'last' => 'ចាន់ថា', 'gender' => 'male', 'dob' => '1995-02-02', 'phone' => '012111222', 'blood' => 'O+', 'allergies' => 'None'],
            ['number' => 'PAT-2026-0012', 'first' => 'ចន់', 'last' => 'សុភា', 'gender' => 'female', 'dob' => '1990-06-15', 'phone' => '012333444', 'blood' => 'A+', 'allergies' => 'Penicillin'],
            ['number' => 'PAT-2026-0013', 'first' => 'កែវ', 'last' => 'ស្រីនាថ', 'gender' => 'female', 'dob' => '1985-09-09', 'phone' => '012555666', 'blood' => 'B+', 'allergies' => 'None'],
            ['number' => 'PAT-2026-0014', 'first' => 'ហេង', 'last' => 'វណ្ណា', 'gender' => 'male', 'dob' => '1979-12-20', 'phone' => '012777888', 'blood' => 'O-', 'allergies' => 'None'],
            ['number' => 'PAT-2026-0015', 'first' => 'លី', 'last' => 'ដារា', 'gender' => 'male', 'dob' => '1993-03-03', 'phone' => '012999000', 'blood' => 'AB+', 'allergies' => 'None'],
            ['number' => 'PAT-2026-0016', 'first' => 'ព្រាប', 'last' => 'មុនី', 'gender' => 'female', 'dob' => '1988-07-25', 'phone' => '012123456', 'blood' => 'A-', 'allergies' => 'Aspirin'],
            ['number' => 'PAT-2026-0017', 'first' => 'ទូច', 'last' => 'សុវណ្ណ', 'gender' => 'male', 'dob' => '2000-01-10', 'phone' => '012987654', 'blood' => 'B-', 'allergies' => 'None'],
            ['number' => 'PAT-2026-0018', 'first' => 'មាស', 'last' => 'បូរិន', 'gender' => 'female', 'dob' => '1982-11-11', 'phone' => '012456789', 'blood' => 'O+', 'allergies' => 'None'],
            ['number' => 'PAT-2026-0019', 'first' => 'សំ', 'last' => 'រតនា', 'gender' => 'male', 'dob' => '1975-04-04', 'phone' => '012654321', 'blood' => 'A+', 'allergies' => 'Seafood'],
            ['number' => 'PAT-2026-0020', 'first' => 'យ៉ែម', 'last' => 'ចន្ថា', 'gender' => 'female', 'dob' => '1996-08-19', 'phone' => '012765432', 'blood' => 'AB-', 'allergies' => 'None'],
        ];

        foreach ($newPatients as $index => $definition) {
            Patient::updateOrCreate(
                ['patient_number' => $definition['number']],
                [
                    'first_name' => $definition['first'],
                    'last_name' => $definition['last'],
                    'gender' => $definition['gender'],
                    'date_of_birth' => $definition['dob'],
                    'phone_number' => $definition['phone'],
                    'email' => strtolower($definition['number']).'@example.com',
                    'address' => 'ភ្នំពេញ ខណ្ឌចំការមន',
                    'blood_type' => $definition['blood'],
                    'allergies' => $definition['allergies'],
                    'emergency_contact_name' => $definition['first'].' '.$definition['last'],
                    'emergency_contact_phone' => $definition['phone'],
                    'notes' => null,
                    'created_at' => $today->copy()->subDays(14 - $index)->setTime(9, 0),
                ],
            );
        }

        $patients = Patient::query()->orderBy('id')->get();
        $medicines = Medicine::query()->orderBy('id')->take(6)->get();

        $appointments = [];
        for ($day = 13; $day >= 0; $day--) {
            $count = 1 + (($day * 7) % 3);

            for ($slot = 0; $slot < $count; $slot++) {
                $appointments[] = [
                    'day' => $day,
                    'slot' => $slot,
                    'patient' => (($day * 3 + $slot) % $patients->count()),
                    'doctor' => (($day + $slot) % $doctors->count()),
                    'time' => sprintf('%02d:%02d', 8 + (($slot * 2) % 8), ($slot * 15) % 60),
                ];
            }
        }

        $sequence = 1;

        foreach ($appointments as $appointment) {
            $patient = $patients[$appointment['patient']];
            $doctor = $doctors[$appointment['doctor']];
            $date = $today->copy()->subDays($appointment['day']);
            // A distinct "R" prefix keeps these rows from colliding with the
            // Khmer demo encounters or any appointments created elsewhere.
            $number = 'APT-2026-R'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);

            $status = match (true) {
                $appointment['day'] === 0 => 'confirmed',
                ($appointment['day'] + $appointment['slot']) % 11 === 0 => 'no_show',
                ($appointment['day'] + $appointment['slot']) % 13 === 0 => 'cancelled',
                default => 'completed',
            };

            $model = Appointment::updateOrCreate(
                ['appointment_number' => $number],
                [
                    'patient_id' => $patient->id,
                    'doctor_id' => $doctor->id,
                    'appointment_date' => $date->toDateString(),
                    'appointment_time' => $appointment['time'],
                    'reason' => 'General consultation and follow-up',
                    'status' => $status,
                    'note' => 'Seeded recent activity.',
                    'created_at' => $date->copy()->setTime(8, 0),
                ],
            );

            if ($status !== 'completed' || $appointment['slot'] % 2 === 0) {
                $sequence++;

                continue;
            }

            $this->seedRecentInvoice($sequence, $model, $patient, $doctor, $receptionist, $medicines, $date);
            $sequence++;
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Medicine>  $medicines
     */
    private function seedRecentInvoice(
        int $sequence,
        Appointment $appointment,
        Patient $patient,
        User $doctor,
        ?User $receptionist,
        $medicines,
        Carbon $date,
    ): void {
        $invoiceNumber = 'INV-2026-R'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);

        if (Invoice::query()->where('invoice_number', $invoiceNumber)->exists()) {
            return;
        }

        $consultationFee = 15.00;
        $medicine = $medicines[$sequence % $medicines->count()];
        $medicineQty = 1 + ($sequence % 3);
        $medicineTotal = round((float) $medicine->price * $medicineQty, 2);
        $subtotal = round($consultationFee + $medicineTotal, 2);
        $amountPaid = $sequence % 4 === 0 ? 0 : $subtotal;
        $balance = round($subtotal - $amountPaid, 2);
        $status = $amountPaid <= 0 ? 'unpaid' : ($amountPaid >= $subtotal ? 'paid' : 'partially_paid');

        $invoice = Invoice::create([
            'invoice_number' => $invoiceNumber,
            'patient_id' => $patient->id,
            'consultation_id' => null,
            'admission_id' => null,
            'status' => $status,
            'invoice_type' => 'outpatient',
            'subtotal' => $subtotal,
            'discount' => 0,
            'tax' => 0,
            'total' => $subtotal,
            'amount_paid' => $amountPaid,
            'balance' => $balance,
            'issued_at' => $date->toDateString(),
            'due_date' => $date->toDateString(),
            'notes' => 'Seeded recent consultation invoice.',
            'created_by' => $receptionist?->id,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'medicine_id' => null,
            'description' => 'Consultation fee',
            'quantity' => 1,
            'unit_price' => $consultationFee,
            'amount' => $consultationFee,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'medicine_id' => $medicine->id,
            'description' => $medicine->name.' '.$medicine->strength,
            'quantity' => $medicineQty,
            'unit_price' => $medicine->price,
            'amount' => $medicineTotal,
        ]);

        if ($amountPaid > 0) {
            Payment::create([
                'invoice_id' => $invoice->id,
                'amount' => $amountPaid,
                'method' => 'cash',
                'reference' => 'PAY-2026-R'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
                'idempotency_key' => (string) Str::uuid(),
                'paid_at' => $date->copy()->setTime(11, 0),
                'received_by' => $receptionist?->id,
                'notes' => $status === 'paid' ? 'Paid in full' : 'Part payment',
            ]);
        }
    }

    // ----------------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------------

    private function pseudoRandom(string $seed, int $min, int $max): int
    {
        if ($max <= $min) {
            return $min;
        }

        return $min + (crc32($seed) % ($max - $min + 1));
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

    private function pdfEscape(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }
}
