<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\StaffDocument;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Services\Auth\SecurityEventService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaffDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function adminUser(): User
    {
        $existing = User::where('email', 'admin@clinic.local')->first();
        if ($existing) {
            return $existing;
        }

        $admin = User::factory()->create([
            'email' => 'admin@clinic.local',
            'account_status' => User::STATUS_ACTIVE,
        ]);
        $admin->assignRole('Admin');

        return $admin;
    }

    private function doctorUser(): User
    {
        $existing = User::where('email', 'doctor@clinic.local')->first();
        if ($existing) {
            return $existing;
        }

        $doctor = User::factory()->create([
            'email' => 'doctor@clinic.local',
            'account_status' => User::STATUS_ACTIVE,
        ]);
        $doctor->assignRole('Doctor');

        return $doctor;
    }

    private function stepUpToken(string $action, ?User $admin = null): string
    {
        $admin = $admin ?? $this->adminUser();

        $res = $this->actingAs($admin)->postJson('/api/v1/auth/step-up', [
            'password' => 'password',
            'action' => $action,
        ]);

        $res->assertOk();

        return (string) $res->json('step_up_token');
    }

    private function fakePdf(string $name): UploadedFile
    {
        $pdf = "%PDF-1.4\n"
            ."%\xE2\xE3\xCF\xD3\n"
            ."1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            ."2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n"
            ."trailer\n<< /Root 1 0 R >>\n%%EOF";

        return UploadedFile::fake()->createWithContent($name, $pdf);
    }

    private function useWebGuard(): void
    {
        $this->app['auth']->shouldUse('web');
    }

    public function test_admin_can_list_staff_directory_with_filters(): void
    {
        $admin = $this->adminUser();

        $this->assertTrue($admin->can('staff.view'));

        $this->actingAs($admin)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('roles.0', 'Admin')
            ->assertJsonFragment(['staff.view']);

        Staff::create([
            'staff_number' => 'STF-0001',
            'first_name' => 'John',
            'last_name' => 'Cleaner',
            'department' => 'Maintenance',
            'job_title' => 'Janitor',
            'employment_status' => 'employed',
            'employment_type' => 'full_time',
        ]);

        Staff::create([
            'staff_number' => 'STF-0002',
            'first_name' => 'Sarah',
            'last_name' => 'Nurse',
            'department' => 'Inpatient',
            'job_title' => 'Ward Nurse',
            'employment_status' => 'on_leave',
            'employment_type' => 'part_time',
        ]);

        $res = $this->actingAs($admin)->getJson('/api/v1/staff?sort=staff_number&direction=asc');

        $res->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.staff_number', 'STF-0001')
            ->assertJsonPath('data.1.staff_number', 'STF-0002');

        // Test filter by department
        $depRes = $this->actingAs($admin)->getJson('/api/v1/staff?department=Maintenance');
        $depRes->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.staff_number', 'STF-0001');

        // Test filter by employment status
        $statusRes = $this->actingAs($admin)->getJson('/api/v1/staff?employment_status=on_leave');
        $statusRes->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.staff_number', 'STF-0002');
    }

    public function test_admin_can_create_staff_member_without_user_account(): void
    {
        $admin = $this->adminUser();

        $payload = [
            'first_name' => 'Alice',
            'last_name' => 'Technician',
            'phone' => '+1555123456',
            'email' => 'alice.tech@clinic.local',
            'department' => 'Radiology',
            'job_title' => 'X-Ray Tech',
            'employment_type' => 'full_time',
            'employment_status' => 'employed',
            'hire_date' => '2026-01-15',
            'salary_amount' => 4500.00,
            'pay_frequency' => 'monthly',
            'allowances' => 200.00,
        ];

        $res = $this->actingAs($admin)->postJson('/api/v1/staff', $payload);

        $res->assertCreated()
            ->assertJsonPath('staff.first_name', 'Alice')
            ->assertJsonPath('staff.last_name', 'Technician')
            ->assertJsonPath('staff.department', 'Radiology');

        $this->assertDatabaseHas('staff', [
            'first_name' => 'Alice',
            'last_name' => 'Technician',
            'user_id' => null,
            'employment_status' => 'employed',
        ]);

        // Auto-generated staff number
        $staff = Staff::where('email', 'alice.tech@clinic.local')->first();
        $this->assertNotNull($staff);
        $this->assertStringStartsWith('STF-', $staff->staff_number);

        // Security event audit
        $this->assertDatabaseHas('security_events', [
            'event' => SecurityEventService::STAFF_CREATED,
            'user_id' => $admin->id,
        ]);
    }

    public function test_employment_status_is_separate_from_account_status(): void
    {
        $admin = $this->adminUser();
        $doctor = $this->doctorUser();

        $staff = Staff::create([
            'staff_number' => 'STF-0010',
            'user_id' => $doctor->id,
            'first_name' => 'Gregory',
            'last_name' => 'House',
            'department' => 'Diagnostics',
            'job_title' => 'Chief of Diagnostics',
            'employment_status' => 'on_leave',
        ]);

        $doctor->update(['staff_id' => $staff->staff_number]);

        // Doctor is on_leave in employment, but their account is active
        $this->assertEquals('on_leave', $staff->fresh()->employment_status);
        $this->assertEquals(User::STATUS_ACTIVE, $doctor->fresh()->account_status);

        // Updating employment status does not alter account_status
        $this->actingAs($admin)->putJson("/api/v1/staff/{$staff->id}", [
            'employment_status' => 'resigned',
        ])->assertOk();

        $this->assertEquals('resigned', $staff->fresh()->employment_status);
        $this->assertEquals(User::STATUS_ACTIVE, $doctor->fresh()->account_status);
    }

    public function test_unauthorized_user_cannot_view_or_update_salary(): void
    {
        $doctor = $this->doctorUser(); // has no staff permissions
        $viewer = User::factory()->create(['account_status' => User::STATUS_ACTIVE]);
        $viewer->givePermissionTo('staff.view');

        $staff = Staff::create([
            'staff_number' => 'STF-0020',
            'first_name' => 'Mark',
            'last_name' => 'Sloan',
            'department' => 'Surgery',
            'salary_amount' => 12000.00,
            'pay_frequency' => 'monthly',
            'allowances' => 1500.00,
        ]);

        $document = StaffDocument::create([
            'staff_id' => $staff->id,
            'uploaded_by' => $this->adminUser()->id,
            'title' => 'Private employment contract',
            'category' => 'contract',
            'original_name' => 'contract.pdf',
            'path' => 'staff-documents/test/contract.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
        ]);

        // Doctor without staff.view is forbidden from viewing staff list and details
        $this->assertFalse($doctor->can('staff.view'));
        $this->actingAs($doctor)->getJson('/api/v1/staff')->assertForbidden();
        $this->actingAs($doctor)->getJson("/api/v1/staff/{$staff->id}")->assertForbidden();

        // User with staff.view can view staff list, but salary is redacted
        $listRes = $this->actingAs($viewer)->getJson('/api/v1/staff');
        $listRes->assertOk();
        $item = $listRes->json('data.0');
        $this->assertArrayNotHasKey('salary_amount', $item);
        $this->assertArrayNotHasKey('allowances', $item);

        // User with staff.view cannot view salary in staff show
        $showRes = $this->actingAs($viewer)->getJson("/api/v1/staff/{$staff->id}");
        $showRes->assertOk();
        $staffData = $showRes->json('staff');
        $this->assertArrayNotHasKey('salary_amount', $staffData);
        $this->assertArrayNotHasKey('salary_histories', $staffData);
        $this->assertArrayNotHasKey('documents', $staffData);

        // Staff-directory visibility alone does not grant access to confidential documents.
        $this->actingAs($viewer)->getJson("/api/v1/staff/{$staff->id}/documents")
            ->assertForbidden();
        $this->actingAs($viewer)->getJson("/api/v1/staff-documents/{$document->id}/download")
            ->assertForbidden();

        // User without staff.salary.update cannot update salary
        $updateRes = $this->actingAs($viewer)->postJson("/api/v1/staff/{$staff->id}/salary", [
            'salary_amount' => 15000.00,
            'pay_frequency' => 'monthly',
        ]);
        $updateRes->assertForbidden();
    }

    public function test_authorized_admin_can_view_and_update_salary_with_audit_trail(): void
    {
        $admin = $this->adminUser();

        $staff = Staff::create([
            'staff_number' => 'STF-0030',
            'first_name' => 'Derek',
            'last_name' => 'Shepherd',
            'department' => 'Neurosurgery',
            'salary_amount' => 15000.00,
            'pay_frequency' => 'monthly',
            'allowances' => 1000.00,
        ]);

        // Admin views salary
        $showRes = $this->actingAs($admin)->getJson("/api/v1/staff/{$staff->id}");
        $showRes->assertOk();
        $this->assertEquals('15000.00', $showRes->json('staff.salary_amount'));

        // Admin updates salary
        $updateRes = $this->actingAs($admin)->postJson("/api/v1/staff/{$staff->id}/salary", [
            'salary_amount' => 18000.00,
            'pay_frequency' => 'monthly',
            'allowances' => 1200.00,
            'effective_from' => '2026-03-01',
            'note' => 'Department head promotion',
        ]);

        $updateRes->assertOk()
            ->assertJsonPath('message', 'Compensation updated successfully.')
            ->assertJsonPath('history.amount', '18000.00')
            ->assertJsonPath('history.note', 'Department head promotion');

        // Database checks
        $this->assertDatabaseHas('staff', [
            'id' => $staff->id,
            'salary_amount' => 18000.00,
            'allowances' => 1200.00,
        ]);

        $this->assertDatabaseHas('staff_salary_histories', [
            'staff_id' => $staff->id,
            'changed_by' => $admin->id,
            'amount' => 18000.00,
            'note' => 'Department head promotion',
        ]);

        // Security event logged
        $this->assertDatabaseHas('security_events', [
            'event' => SecurityEventService::STAFF_SALARY_UPDATED,
            'user_id' => $admin->id,
        ]);
    }

    public function test_confidential_documents_upload_download_and_deletion(): void
    {
        Storage::fake('local');
        $admin = $this->adminUser();
        $doctor = $this->doctorUser();

        $staff = Staff::create([
            'staff_number' => 'STF-0040',
            'first_name' => 'Cristina',
            'last_name' => 'Yang',
            'department' => 'Cardiothoracic',
        ]);

        $file = $this->fakePdf('medical_license.pdf');

        // Admin uploads document
        $uploadRes = $this->actingAs($admin)->postJson("/api/v1/staff/{$staff->id}/documents", [
            'title' => 'Medical Board License 2026',
            'category' => 'license',
            'file' => $file,
        ]);

        $uploadRes->assertCreated()
            ->assertJsonPath('document.title', 'Medical Board License 2026')
            ->assertJsonPath('document.category', 'license');

        $docId = $uploadRes->json('document.id');

        $this->assertDatabaseHas('staff_documents', [
            'id' => $docId,
            'staff_id' => $staff->id,
            'uploaded_by' => $admin->id,
            'title' => 'Medical Board License 2026',
        ]);

        // Security event logged
        $this->assertDatabaseHas('security_events', [
            'event' => SecurityEventService::STAFF_DOCUMENT_UPLOADED,
            'user_id' => $admin->id,
        ]);

        // Admin downloads document
        $downloadRes = $this->actingAs($admin)->get("/api/v1/staff-documents/{$docId}/download");
        $downloadRes->assertOk();

        // Download event logged
        $this->assertDatabaseHas('security_events', [
            'event' => SecurityEventService::STAFF_DOCUMENT_DOWNLOADED,
            'user_id' => $admin->id,
        ]);

        // Unauthorized user cannot delete
        $this->actingAs($doctor)->deleteJson("/api/v1/staff-documents/{$docId}")
            ->assertForbidden();

        // Admin deletes document
        $deleteRes = $this->actingAs($admin)->deleteJson("/api/v1/staff-documents/{$docId}");
        $deleteRes->assertOk();

        $this->assertDatabaseMissing('staff_documents', ['id' => $docId]);

        $this->assertDatabaseHas('security_events', [
            'event' => SecurityEventService::STAFF_DOCUMENT_DELETED,
            'user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_link_existing_user_to_staff_profile(): void
    {
        $admin = $this->adminUser();
        $doctor = $this->doctorUser();

        $staff = Staff::create([
            'staff_number' => 'STF-0050',
            'first_name' => 'Miranda',
            'last_name' => 'Bailey',
            'department' => 'General Surgery',
        ]);

        $this->assertNull($staff->user_id);
        $this->assertNull($doctor->staff_id);

        $linkRes = $this->actingAs($admin)->postJson("/api/v1/staff/{$staff->id}/link-user", [
            'user_id' => $doctor->id,
        ]);

        $linkRes->assertOk()
            ->assertJsonPath('staff.user_id', $doctor->id);

        $this->assertEquals($doctor->id, $staff->fresh()->user_id);
        $this->assertEquals('STF-0050', $doctor->fresh()->staff_id);

        // Audit event logged
        $this->assertDatabaseHas('security_events', [
            'event' => SecurityEventService::STAFF_USER_LINKED,
            'user_id' => $admin->id,
        ]);
    }

    public function test_cannot_link_user_already_linked_to_another_staff_member(): void
    {
        $admin = $this->adminUser();
        $doctor = $this->doctorUser();

        $staff1 = Staff::create([
            'staff_number' => 'STF-0061',
            'first_name' => 'Staff',
            'last_name' => 'One',
            'user_id' => $doctor->id,
        ]);

        $staff2 = Staff::create([
            'staff_number' => 'STF-0062',
            'first_name' => 'Staff',
            'last_name' => 'Two',
        ]);

        $res = $this->actingAs($admin)->postJson("/api/v1/staff/{$staff2->id}/link-user", [
            'user_id' => $doctor->id,
        ]);

        $res->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_staff_invitation_with_staff_id_links_profile_upon_acceptance_and_approval(): void
    {
        $admin = $this->adminUser();

        $staff = Staff::create([
            'staff_number' => 'STF-0070',
            'first_name' => 'Owen',
            'last_name' => 'Hunt',
            'email' => 'owen.hunt@clinic.local',
            'department' => 'Trauma',
        ]);

        // 1. Admin sends invitation targeting existing staff profile
        $inviteRes = $this->actingAs($admin)->postJson('/api/v1/staff/invitations', [
            'staff_id' => $staff->id,
            'name' => 'Owen Hunt',
            'email' => 'owen.hunt@clinic.local',
            'role' => 'Doctor',
        ]);

        $inviteRes->assertCreated()
            ->assertJsonPath('invitation.staff_id', $staff->id);

        $invitation = StaffInvitation::where('email', 'owen.hunt@clinic.local')->first();
        $this->assertNotNull($invitation);
        $this->assertEquals($staff->id, $invitation->staff_id);

        // Re-generate token string or inspect
        $pendingUser = User::where('email', 'owen.hunt@clinic.local')->first();
        $this->assertNotNull($pendingUser);
        $this->assertEquals(User::STATUS_PENDING, $pendingUser->account_status);

        // 2. Invitee accepts invitation
        $token = 'test-token-70';
        $invitation->update(['token_hash' => hash('sha256', $token)]);

        $acceptRes = $this->postJson('/api/v1/auth/invitation/accept', [
            'token' => $token,
            'username' => 'owen.hunt',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        $acceptRes->assertOk();

        // Account is now pending_approval and linked to the staff profile!
        $acceptedUser = $pendingUser->fresh();
        $this->assertEquals(User::STATUS_PENDING_APPROVAL, $acceptedUser->account_status);
        $this->assertEquals('STF-0070', $acceptedUser->staff_id);
        $this->assertEquals($acceptedUser->id, $staff->fresh()->user_id);

        // Pending approval account cannot login yet
        $this->useWebGuard();
        $this->postJson('/api/v1/auth/login', [
            'email' => 'owen.hunt@clinic.local',
            'password' => 'SecurePass123!',
        ])->assertStatus(403);

        // 3. Admin approves account
        $stepUp = $this->stepUpToken('users.approve', $admin);
        $this->actingAs($admin)->postJson(
            "/api/v1/users/{$acceptedUser->id}/approve",
            [],
            ['X-Step-Up-Token' => $stepUp],
        )->assertOk();

        $this->assertEquals(User::STATUS_ACTIVE, $acceptedUser->fresh()->account_status);

        // Now approved doctor can sign in
        $this->useWebGuard();
        $loginRes = $this->postJson('/api/v1/auth/login', [
            'email' => 'owen.hunt@clinic.local',
            'password' => 'SecurePass123!',
        ]);

        $loginRes->assertOk()->assertJsonPath('message', 'Login successful.');
    }

    public function test_admin_can_unlink_user_from_staff_profile(): void
    {
        $admin = $this->adminUser();
        $doctor = $this->doctorUser();

        $staff = Staff::create([
            'staff_number' => 'STF-0080',
            'first_name' => 'April',
            'last_name' => 'Kepner',
            'user_id' => $doctor->id,
        ]);

        $doctor->update(['staff_id' => $staff->staff_number]);

        $unlinkRes = $this->actingAs($admin)->deleteJson("/api/v1/staff/{$staff->id}/unlink-user");

        $unlinkRes->assertOk()
            ->assertJsonPath('staff.user_id', null);

        $this->assertNull($staff->fresh()->user_id);
        $this->assertNull($doctor->fresh()->staff_id);

        $this->assertDatabaseHas('security_events', [
            'event' => SecurityEventService::STAFF_USER_UNLINKED,
            'user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_create_staff_member_with_cv_document(): void
    {
        Storage::fake('local');
        $admin = $this->adminUser();

        $cv = $this->fakePdf('jane_doe_cv.pdf');

        $res = $this->actingAs($admin)->postJson('/api/v1/staff', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane.doe@clinic.local',
            'job_title' => 'Staff Nurse',
            'employment_type' => 'full_time',
            'employment_status' => 'employed',
            'cv' => $cv,
        ]);

        $res->assertCreated()
            ->assertJsonPath('staff.first_name', 'Jane')
            ->assertJsonPath('staff.last_name', 'Doe');

        $staff = Staff::where('email', 'jane.doe@clinic.local')->first();
        $this->assertNotNull($staff);

        // CV is stored as a confidential staff document in the CV category.
        $this->assertDatabaseHas('staff_documents', [
            'staff_id' => $staff->id,
            'uploaded_by' => $admin->id,
            'category' => StaffDocument::CATEGORY_CV,
            'title' => 'Curriculum Vitae',
            'original_name' => 'jane_doe_cv.pdf',
        ]);

        Storage::disk('local')->assertExists(
            $staff->documents()->where('category', StaffDocument::CATEGORY_CV)->value('path')
        );

        // Both the staff creation and document upload are audited.
        $this->assertDatabaseHas('security_events', [
            'event' => SecurityEventService::STAFF_CREATED,
            'user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('security_events', [
            'event' => SecurityEventService::STAFF_DOCUMENT_UPLOADED,
            'user_id' => $admin->id,
        ]);
    }

    public function test_creating_staff_with_disallowed_cv_type_is_rejected(): void
    {
        Storage::fake('local');
        $admin = $this->adminUser();

        $badFile = UploadedFile::fake()->create('malicious.exe', 10, 'application/octet-stream');

        $res = $this->actingAs($admin)->postJson('/api/v1/staff', [
            'first_name' => 'Eve',
            'last_name' => 'Mallory',
            'cv' => $badFile,
        ]);

        $res->assertUnprocessable()
            ->assertJsonValidationErrors(['cv']);

        // The staff record must not be persisted when the CV is rejected.
        $this->assertDatabaseMissing('staff', ['first_name' => 'Eve', 'last_name' => 'Mallory']);
        $this->assertDatabaseCount('staff_documents', 0);
    }
}
