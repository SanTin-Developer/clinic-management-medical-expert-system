<?php

namespace Tests\Feature;

use App\Models\AttendanceSession;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\ShiftTemplate;
use App\Models\Staff;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class AttendanceSessionTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        config([
            'attendance.timezone' => 'Asia/Phnom_Penh',
            'attendance.qr_ttl' => 5,
        ]);
    }

    public function test_admin_opens_a_session_and_staff_scans_to_clock_in(): void
    {
        $admin = $this->actingAsRole('Head Nurse');
        $staff = $this->staffUser('Nurse');
        $template = $this->template();
        $date = '2030-06-01';

        $this->assign($staff, $template, $date);
        $this->travelTo(Carbon::parse('2030-06-01 00:30:00', 'UTC'));

        $sessionId = $this->openSession($admin, $template, $date);
        $token = $this->issueToken($admin, $sessionId);

        Sanctum::actingAs($staff);
        $this->postJson('/api/v1/attendance/scan', ['token' => $token, 'action' => 'clock_in'])
            ->assertOk()
            ->assertJsonPath('data.status', 'present')
            ->assertJsonPath('data.clock_in_source', 'qr');

        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $staff->id,
            'shift_template_id' => $template->id,
            'status' => 'present',
            'clock_in_session_id' => $sessionId,
        ]);

        $this->assertDatabaseHas('attendance_audit_logs', ['event' => 'attendance.scan_accepted']);
    }

    public function test_lateness_bands_are_computed_from_the_scheduled_start(): void
    {
        $admin = $this->actingAsRole('Head Nurse');
        $staff = $this->staffUser('Nurse');
        $template = $this->template();

        $cases = [
            '2030-06-01 00:30:00' => [0, 'present', null],
            '2030-06-02 00:40:00' => [10, 'late', 'Late 10'],
            '2030-06-03 00:50:00' => [20, 'late', 'Late 20'],
            '2030-06-04 00:59:00' => [29, 'late', 'Late 30'],
        ];

        foreach ($cases as $now => [$minutes, $status, $band]) {
            $date = substr($now, 0, 10);
            $this->assign($staff, $template, $date);
            $this->travelTo(Carbon::parse($now, 'UTC'));

            $sessionId = $this->openSession($admin, $template, $date);
            $token = $this->issueToken($admin, $sessionId);

            Sanctum::actingAs($staff);
            $this->postJson('/api/v1/attendance/scan', ['token' => $token, 'action' => 'clock_in'])
                ->assertOk()
                ->assertJsonPath('data.late_minutes', $minutes)
                ->assertJsonPath('data.status', $status)
                ->assertJsonPath('data.late_band', $band);
        }
    }

    public function test_scan_after_the_clock_in_cutoff_is_rejected(): void
    {
        $admin = $this->actingAsRole('Head Nurse');
        $staff = $this->staffUser('Nurse');
        $template = $this->template();
        $date = '2030-06-05';

        $this->assign($staff, $template, $date);
        $this->travelTo(Carbon::parse('2030-06-05 00:59:58', 'UTC'));

        $sessionId = $this->openSession($admin, $template, $date);
        $token = $this->issueToken($admin, $sessionId);

        // Cutoff is 08:00 clinic time = 01:00 UTC; the token is still valid for
        // a further two seconds, so the rejection must be the closed session.
        $this->travelTo(Carbon::parse('2030-06-05 01:00:01', 'UTC'));

        Sanctum::actingAs($staff);
        $this->postJson('/api/v1/attendance/scan', ['token' => $token, 'action' => 'clock_in'])
            ->assertStatus(409)
            ->assertJsonPath('reason', 'session_closed');

        $this->assertDatabaseHas('attendance_sessions', [
            'id' => $sessionId,
            'status' => AttendanceSession::STATUS_CLOSED,
        ]);
    }

    public function test_expired_token_is_rejected(): void
    {
        $admin = $this->actingAsRole('Head Nurse');
        $staff = $this->staffUser('Nurse');
        $template = $this->template();
        $date = '2030-06-06';

        $this->assign($staff, $template, $date);
        $this->travelTo(Carbon::parse('2030-06-06 00:40:00', 'UTC'));

        $sessionId = $this->openSession($admin, $template, $date);
        $token = $this->issueToken($admin, $sessionId);

        $this->travelTo(Carbon::parse('2030-06-06 00:40:06', 'UTC'));

        Sanctum::actingAs($staff);
        $this->postJson('/api/v1/attendance/scan', ['token' => $token, 'action' => 'clock_in'])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'expired_token');
    }

    public function test_revoked_token_is_rejected_after_the_session_closes(): void
    {
        $admin = $this->actingAsRole('Head Nurse');
        $staff = $this->staffUser('Nurse');
        $template = $this->template();
        $date = '2030-06-07';

        $this->assign($staff, $template, $date);
        $this->travelTo(Carbon::parse('2030-06-07 00:40:00', 'UTC'));

        $sessionId = $this->openSession($admin, $template, $date);
        $token = $this->issueToken($admin, $sessionId);

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/attendance/sessions/{$sessionId}/close")->assertOk();

        Sanctum::actingAs($staff);
        $this->postJson('/api/v1/attendance/scan', ['token' => $token, 'action' => 'clock_in'])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'revoked_token');
    }

    public function test_unknown_token_is_rejected(): void
    {
        $this->actingAsRole('Head Nurse');
        $staff = $this->staffUser('Nurse');

        Sanctum::actingAs($staff);
        $this->postJson('/api/v1/attendance/scan', ['token' => 'not-a-real-token', 'action' => 'clock_in'])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'invalid_token');
    }

    public function test_scan_with_the_wrong_action_is_rejected(): void
    {
        $admin = $this->actingAsRole('Head Nurse');
        $staff = $this->staffUser('Nurse');
        $template = $this->template();
        $date = '2030-06-08';

        $this->assign($staff, $template, $date);
        $this->travelTo(Carbon::parse('2030-06-08 00:40:00', 'UTC'));

        $sessionId = $this->openSession($admin, $template, $date, 'clock_in');
        $token = $this->issueToken($admin, $sessionId);

        Sanctum::actingAs($staff);
        $this->postJson('/api/v1/attendance/scan', ['token' => $token, 'action' => 'clock_out'])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'wrong_action');
    }

    public function test_staff_without_an_assigned_shift_is_rejected(): void
    {
        $admin = $this->actingAsRole('Head Nurse');
        $staff = $this->staffUser('Nurse');
        $template = $this->template();
        $date = '2030-06-09';

        $this->travelTo(Carbon::parse('2030-06-09 00:40:00', 'UTC'));

        $sessionId = $this->openSession($admin, $template, $date);
        $token = $this->issueToken($admin, $sessionId);

        Sanctum::actingAs($staff);
        $this->postJson('/api/v1/attendance/scan', ['token' => $token, 'action' => 'clock_in'])
            ->assertStatus(403)
            ->assertJsonPath('reason', 'not_assigned');
    }

    public function test_staff_on_approved_leave_are_rejected(): void
    {
        $admin = $this->actingAsRole('Head Nurse');
        $staff = $this->staffUser('Nurse');
        $template = $this->template();
        $date = '2030-06-10';

        $this->assign($staff, $template, $date);
        LeaveRequest::factory()->approved()->create([
            'user_id' => $staff->id,
            'start_date' => $date,
            'end_date' => $date,
        ]);

        $this->travelTo(Carbon::parse('2030-06-10 00:40:00', 'UTC'));

        $sessionId = $this->openSession($admin, $template, $date);
        $token = $this->issueToken($admin, $sessionId);

        Sanctum::actingAs($staff);
        $this->postJson('/api/v1/attendance/scan', ['token' => $token, 'action' => 'clock_in'])
            ->assertStatus(403)
            ->assertJsonPath('reason', 'on_leave');
    }

    public function test_a_second_clock_in_is_rejected_as_a_duplicate(): void
    {
        $admin = $this->actingAsRole('Head Nurse');
        $staff = $this->staffUser('Nurse');
        $template = $this->template();
        $date = '2030-06-11';

        $this->assign($staff, $template, $date);
        $this->travelTo(Carbon::parse('2030-06-11 00:40:00', 'UTC'));

        $sessionId = $this->openSession($admin, $template, $date);
        $token = $this->issueToken($admin, $sessionId);

        Sanctum::actingAs($staff);
        $this->postJson('/api/v1/attendance/scan', ['token' => $token, 'action' => 'clock_in'])->assertOk();
        $this->postJson('/api/v1/attendance/scan', ['token' => $token, 'action' => 'clock_in'])
            ->assertStatus(409)
            ->assertJsonPath('reason', 'duplicate');
    }

    public function test_tokens_rotate_between_requests(): void
    {
        $admin = $this->actingAsRole('Head Nurse');
        $template = $this->template();
        $date = '2030-06-12';

        $this->travelTo(Carbon::parse('2030-06-12 00:40:00', 'UTC'));

        $sessionId = $this->openSession($admin, $template, $date);
        $first = $this->issueToken($admin, $sessionId);
        $second = $this->issueToken($admin, $sessionId);

        $this->assertNotSame($first, $second);
        $this->assertDatabaseCount('attendance_session_tokens', 2);
    }

    public function test_session_dashboard_tracks_scanned_and_unscanned_staff(): void
    {
        $admin = $this->actingAsRole('Head Nurse');
        $staffA = $this->staffUser('Nurse');
        $staffB = $this->staffUser('Nurse');
        $template = $this->template();
        $date = '2030-06-13';

        $this->assign($staffA, $template, $date);
        $this->assign($staffB, $template, $date);
        $this->travelTo(Carbon::parse('2030-06-13 00:40:00', 'UTC'));

        $sessionId = $this->openSession($admin, $template, $date);
        $token = $this->issueToken($admin, $sessionId);

        Sanctum::actingAs($staffA);
        $this->postJson('/api/v1/attendance/scan', ['token' => $token, 'action' => 'clock_in'])->assertOk();

        Sanctum::actingAs($admin);
        $this->getJson("/api/v1/attendance/sessions/{$sessionId}")
            ->assertOk()
            ->assertJsonPath('data.stats.assigned', 2)
            ->assertJsonPath('data.stats.scanned', 1)
            ->assertJsonPath('data.stats.unscanned', 1);
    }

    public function test_nurse_cannot_open_a_session(): void
    {
        $this->actingAsRole('Nurse');
        $template = $this->template();

        $this->postJson('/api/v1/attendance/sessions', [
            'shift_template_id' => $template->id,
            'session_date' => '2030-06-14',
            'action' => 'clock_in',
        ])->assertForbidden();
    }

    public function test_clock_out_requires_a_prior_clock_in(): void
    {
        $admin = $this->actingAsRole('Head Nurse');
        $staff = $this->staffUser('Nurse');
        $template = $this->template();
        $date = '2030-06-15';

        $this->assign($staff, $template, $date);
        $this->travelTo(Carbon::parse('2030-06-15 07:40:00', 'UTC'));

        $sessionId = $this->openSession($admin, $template, $date, 'clock_out');
        $token = $this->issueToken($admin, $sessionId);

        Sanctum::actingAs($staff);
        $this->postJson('/api/v1/attendance/scan', ['token' => $token, 'action' => 'clock_out'])
            ->assertStatus(409)
            ->assertJsonPath('reason', 'conflicting_attendance');
    }

    private function staffUser(string $role = 'Nurse', array $overrides = []): User
    {
        $user = $this->userWithRole($role);

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
            'crosses_midnight' => $template->crosses_midnight,
            'type' => $template->type,
            'status' => 'scheduled',
        ], $overrides));
    }

    private function openSession(User $admin, ShiftTemplate $template, string $date, string $action = 'clock_in'): int
    {
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/attendance/sessions', [
            'shift_template_id' => $template->id,
            'session_date' => $date,
            'action' => $action,
        ])->assertCreated();

        return $response->json('data.session.id');
    }

    private function issueToken(User $admin, int $sessionId): string
    {
        Sanctum::actingAs($admin);

        return $this->getJson("/api/v1/attendance/sessions/{$sessionId}/token")
            ->assertOk()
            ->json('data.token');
    }
}
