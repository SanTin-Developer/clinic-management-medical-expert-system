<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\ClinicHoliday;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\ShiftTemplate;
use App\Models\Staff;
use App\Models\User;
use App\Services\Attendance\AttendanceFinalizationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class AttendanceFinalizationTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        config(['attendance.timezone' => 'Asia/Phnom_Penh']);
    }

    public function test_a_scheduled_staff_member_who_never_clocks_in_is_marked_absent(): void
    {
        $staff = $this->staffUser();
        $template = $this->template();
        $date = '2030-06-01';

        $this->assign($staff, $template, $date);
        $this->travelTo(Carbon::parse('2030-06-01 09:00:00', 'UTC'));

        $touched = $this->finalization()->finalizeTemplate($template, $date);

        $this->assertSame(1, $touched);
        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $staff->id,
            'status' => 'absent',
        ]);
    }

    public function test_holidays_are_never_marked_absent(): void
    {
        $staff = $this->staffUser();
        $template = $this->template();
        $date = '2030-06-02';

        $this->assign($staff, $template, $date);
        ClinicHoliday::create(['holiday_date' => $date, 'name' => 'Clinic Holiday']);
        $this->travelTo(Carbon::parse('2030-06-02 09:00:00', 'UTC'));

        $this->finalization()->finalizeTemplate($template, $date);

        $this->assertDatabaseMissing('attendance_records', [
            'user_id' => $staff->id,
            'status' => 'absent',
        ]);
    }

    public function test_staff_on_approved_leave_are_finalized_as_on_leave(): void
    {
        $staff = $this->staffUser();
        $template = $this->template();
        $date = '2030-06-03';

        $this->assign($staff, $template, $date);
        LeaveRequest::factory()->approved()->create([
            'user_id' => $staff->id,
            'start_date' => $date,
            'end_date' => $date,
        ]);
        $this->travelTo(Carbon::parse('2030-06-03 09:00:00', 'UTC'));

        $this->finalization()->finalizeTemplate($template, $date);

        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $staff->id,
            'status' => 'on_leave',
        ]);
    }

    public function test_a_clock_in_without_a_clock_out_is_flagged(): void
    {
        $staff = $this->staffUser();
        $template = $this->template();
        $date = '2030-06-04';

        $this->assign($staff, $template, $date);
        AttendanceRecord::factory()->create([
            'user_id' => $staff->id,
            'work_date' => $date,
            'clock_in' => Carbon::parse('2030-06-04 00:35:00', 'UTC'),
            'clock_out' => null,
        ]);
        $this->travelTo(Carbon::parse('2030-06-04 09:00:00', 'UTC'));

        $this->finalization()->finalizeTemplate($template, $date);

        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $staff->id,
            'missing_clock_out' => true,
        ]);
    }

    public function test_finalization_is_idempotent(): void
    {
        $staff = $this->staffUser();
        $template = $this->template();
        $date = '2030-06-05';

        $this->assign($staff, $template, $date);
        $this->travelTo(Carbon::parse('2030-06-05 09:00:00', 'UTC'));

        $this->finalization()->finalizeTemplate($template, $date);
        $this->finalization()->finalizeTemplate($template, $date);

        $this->assertDatabaseCount('attendance_records', 1);
    }

    public function test_the_command_closes_expired_sessions_and_finalizes_absences(): void
    {
        $staff = $this->staffUser();
        $template = $this->template();
        $date = '2030-06-06';

        $this->assign($staff, $template, $date);

        $session = AttendanceSession::factory()->create([
            'shift_template_id' => $template->id,
            'session_date' => $date,
            'action' => 'clock_in',
            'status' => AttendanceSession::STATUS_OPEN,
            'closes_at' => Carbon::parse('2030-06-06 01:00:00', 'UTC'),
        ]);

        $this->travelTo(Carbon::parse('2030-06-06 09:00:00', 'UTC'));

        $this->artisan('attendance:finalize')->assertSuccessful();

        $this->assertDatabaseHas('attendance_sessions', [
            'id' => $session->id,
            'status' => AttendanceSession::STATUS_CLOSED,
            'close_reason' => 'cutoff',
        ]);
        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $staff->id,
            'status' => 'absent',
        ]);
    }

    private function finalization(): AttendanceFinalizationService
    {
        return app(AttendanceFinalizationService::class);
    }

    private function staffUser(array $overrides = []): User
    {
        $user = $this->userWithRole('Nurse');

        Staff::create(array_merge([
            'user_id' => $user->id,
            'staff_number' => 'STF-'.strtoupper(Str::random(8)),
            'first_name' => 'Test',
            'last_name' => 'Staff',
            'department' => 'Inpatient',
            'job_title' => 'Ward Nurse',
            'employment_status' => 'employed',
            'employment_type' => 'full_time',
        ], $overrides));

        return $user->fresh();
    }

    private function template(array $overrides = []): ShiftTemplate
    {
        return ShiftTemplate::factory()->create(array_merge([
            'name' => 'Morning '.Str::random(6),
            'type' => 'day',
            'start_time' => '07:30',
            'clock_in_cutoff' => '08:00',
            'end_time' => '14:30',
            'crosses_midnight' => false,
            'clock_out_opens_at' => '14:30',
            'clock_out_closes_at' => '15:00',
            'is_active' => true,
        ], $overrides));
    }

    private function assign(User $user, ShiftTemplate $template, string $date, array $overrides = []): Shift
    {
        return Shift::factory()->create(array_merge([
            'user_id' => $user->id,
            'shift_template_id' => $template->id,
            'shift_date' => $date,
            'start_time' => substr($template->start_time, 0, 5),
            'end_time' => substr($template->end_time, 0, 5),
            'type' => $template->type,
            'status' => 'scheduled',
        ], $overrides));
    }
}
