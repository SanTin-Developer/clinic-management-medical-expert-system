<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientDocument;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Storage::fake('local');
    }

    public function test_doctor_can_upload_a_document(): void
    {
        $this->actingAsRole('Doctor');
        $patient = Patient::factory()->create();

        $this->postJson("/api/v1/patients/{$patient->id}/documents", [
            'title' => 'Blood test',
            'category' => 'lab-result',
            'file' => UploadedFile::fake()->create('blood-test.pdf', 120, 'application/pdf'),
        ])->assertCreated()->assertJsonPath('data.title', 'Blood test');

        $this->assertDatabaseHas('patient_documents', [
            'patient_id' => $patient->id,
            'category' => 'lab-result',
        ]);
    }

    public function test_receptionist_can_list_but_not_upload_documents(): void
    {
        $this->actingAsRole('Receptionist');
        $patient = Patient::factory()->create();

        $this->getJson("/api/v1/patients/{$patient->id}/documents")->assertOk();

        $this->postJson("/api/v1/patients/{$patient->id}/documents", [
            'title' => 'X',
            'category' => 'other',
            'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        ])->assertForbidden();
    }

    public function test_document_can_be_downloaded_and_deleted(): void
    {
        $this->actingAsRole('Doctor');
        $patient = Patient::factory()->create();

        $documentId = $this->postJson("/api/v1/patients/{$patient->id}/documents", [
            'title' => 'Scan',
            'category' => 'imaging',
            'file' => UploadedFile::fake()->create('scan.png', 30, 'image/png'),
        ])->assertCreated()->json('data.id');

        $document = PatientDocument::findOrFail($documentId);

        Storage::disk('local')->assertExists($document->path);

        $this->get("/api/v1/documents/{$documentId}/download")->assertOk();

        $this->deleteJson("/api/v1/documents/{$documentId}")->assertOk();

        $this->assertDatabaseMissing('patient_documents', ['id' => $documentId]);
        Storage::disk('local')->assertMissing($document->path);
    }

    public function test_upload_rejects_unsupported_file_type(): void
    {
        $this->actingAsRole('Doctor');
        $patient = Patient::factory()->create();

        $this->postJson("/api/v1/patients/{$patient->id}/documents", [
            'title' => 'Script',
            'category' => 'other',
            'file' => UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream'),
        ])->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_user_without_manage_cannot_delete_a_document(): void
    {
        $this->actingAsRole('Doctor');
        $patient = Patient::factory()->create();

        $documentId = $this->postJson("/api/v1/patients/{$patient->id}/documents", [
            'title' => 'Scan',
            'category' => 'imaging',
            'file' => UploadedFile::fake()->create('scan.png', 30, 'image/png'),
        ])->assertCreated()->json('data.id');

        $this->actingAsRole('Receptionist');

        $this->deleteJson("/api/v1/documents/{$documentId}")->assertForbidden();
    }
}
