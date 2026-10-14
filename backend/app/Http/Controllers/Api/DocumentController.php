<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\PatientDocument;
use App\Services\Files\DocumentStorage;
use App\Services\Files\PatientDocumentService;
use App\Services\Nursing\PatientAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class DocumentController extends Controller
{
    public const CATEGORIES = [
        'lab-result',
        'imaging',
        'referral',
        'consent',
        'insurance',
        'other',
    ];

    public function __construct(
        private readonly PatientDocumentService $documents,
        private readonly DocumentStorage $storage,
        private readonly PatientAccessService $access,
    ) {}

    public function index(Request $request, Patient $patient): JsonResponse
    {
        $this->authorize('viewAny', PatientDocument::class);

        abort_unless(
            $this->access->canAccessPatient($request->user(), $patient->id),
            403,
            'You are not assigned to this patient.',
        );

        return response()->json([
            'data' => $patient->documents()
                ->with('uploadedBy')
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request, Patient $patient): JsonResponse
    {
        $this->authorize('create', PatientDocument::class);

        abort_unless(
            $this->access->canAccessPatient($request->user(), $patient->id),
            403,
            'You are not assigned to this patient.',
        );

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'file' => ['required', 'file', 'max:'.(int) config('documents.max_size_kb', 10240)],
        ]);

        $stored = $this->documents->store($validated['file'], $patient);

        $document = $patient->documents()->create([
            'uploaded_by' => $request->user()->id,
            'title' => $validated['title'],
            'category' => $validated['category'],
            'original_name' => $stored['original_name'],
            'path' => $stored['path'],
            'mime_type' => $stored['mime_type'],
            'size' => $stored['size'],
        ]);

        return response()->json([
            'message' => 'Document uploaded successfully.',
            'data' => $document->load('uploadedBy'),
        ], 201);
    }

    public function download(PatientDocument $document): Response
    {
        $this->authorize('view', $document);

        abort_unless($this->storage->exists($document->path), 404, 'File not found.');

        return $this->storage->download($document->path, $document->original_name);
    }

    public function destroy(PatientDocument $document): JsonResponse
    {
        $this->authorize('delete', $document);

        $this->storage->delete($document->path);

        $document->delete();

        return response()->json([
            'message' => 'Document deleted successfully.',
        ]);
    }
}
