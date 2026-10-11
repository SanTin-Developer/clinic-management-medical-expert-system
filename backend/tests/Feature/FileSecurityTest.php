<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Services\Files\FileScanner;
use App\Services\Files\ScanResult;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class FileSecurityTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Storage::fake('local');
    }

    public function test_valid_pdf_is_accepted(): void
    {
        $patient = $this->patient();

        $this->upload($patient, 'report.pdf', $this->pdf())
            ->assertCreated();
    }

    public function test_double_extension_is_rejected(): void
    {
        $patient = $this->patient();

        $this->upload($patient, 'invoice.pdf.exe', $this->pdf())
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_compressed_archive_is_rejected(): void
    {
        $patient = $this->patient();

        $this->upload($patient, 'records.pdf.z', 'z-compressed-bytes')
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_content_must_match_extension(): void
    {
        $patient = $this->patient();

        // PNG bytes masquerading as a PDF.
        $this->upload($patient, 'fake.pdf', base64_decode(self::PNG))
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_infected_file_is_rejected(): void
    {
        $this->app->instance(FileScanner::class, $this->scanner(ScanResult::infected('Eicar-Test-Signature')));

        $patient = $this->patient();

        $this->upload($patient, 'report.pdf', $this->pdf())
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('patient_documents', 0);
    }

    public function test_scan_error_fails_closed(): void
    {
        $this->app->instance(FileScanner::class, $this->scanner(ScanResult::error('clamd unreachable')));

        $patient = $this->patient();

        $this->upload($patient, 'report.pdf', $this->pdf())
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_oversized_upload_is_rejected(): void
    {
        config()->set('documents.max_size_kb', 1);

        $patient = $this->patient();

        $this->upload($patient, 'big.pdf', "%PDF-1.4\n".str_repeat('0', 4096))
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    private function patient(): Patient
    {
        $this->actingAsRole('Doctor');

        return Patient::factory()->create();
    }

    private function upload(Patient $patient, string $name, string $content)
    {
        return $this->postJson("/api/v1/patients/{$patient->id}/documents", [
            'title' => 'Upload',
            'category' => 'other',
            'file' => UploadedFile::fake()->createWithContent($name, $content),
        ]);
    }

    private function pdf(): string
    {
        return "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF";
    }

    private function scanner(ScanResult $result): FileScanner
    {
        return new class($result) implements FileScanner
        {
            public function __construct(private readonly ScanResult $result) {}

            public function scan(string $absolutePath, string $originalName): ScanResult
            {
                return $this->result;
            }
        };
    }
}
