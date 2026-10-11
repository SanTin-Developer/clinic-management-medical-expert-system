<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\PatientDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function index(Patient $patient): JsonResponse
    {
        $this->authorize('viewAny', PatientDocument::class);

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

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx', 'max:10240'],
        ]);

        $file = $validated['file'];
        $path = $file->store("patient-documents/{$patient->id}", 'local');

        $document = $patient->documents()->create([
            'uploaded_by' => $request->user()->id,
            'title' => $validated['title'],
            'category' => $validated['category'],
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize() ?: 0,
        ]);

        return response()->json([
            'message' => 'Document uploaded successfully.',
            'data' => $document->load('uploadedBy'),
        ], 201);
    }

    public function download(PatientDocument $document): StreamedResponse
    {
        $this->authorize('view', $document);

        abort_unless(Storage::disk('local')->exists($document->path), 404, 'File not found.');

        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    public function destroy(PatientDocument $document): JsonResponse
    {
        $this->authorize('delete', $document);

        Storage::disk('local')->delete($document->path);

        $document->delete();

        return response()->json([
            'message' => 'Document deleted successfully.',
        ]);
    }
}
