<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\UploadStaffDocumentRequest;
use App\Models\Staff;
use App\Models\StaffDocument;
use App\Services\Auth\SecurityEventService;
use App\Services\Files\DocumentStorage;
use App\Services\Files\StaffDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StaffDocumentController extends Controller
{
    public function __construct(
        private readonly StaffDocumentService $documents,
        private readonly DocumentStorage $storage,
        private readonly SecurityEventService $events,
    ) {}

    /**
     * Display a list of documents for a staff member.
     */
    public function index(Request $request, Staff $staff): JsonResponse
    {
        $this->authorize('viewForStaff', [StaffDocument::class, $staff]);

        return response()->json([
            'documents' => $staff->documents()
                ->with('uploadedBy:id,name')
                ->latest()
                ->get(),
        ]);
    }

    /**
     * Upload and store a confidential staff document.
     */
    public function store(UploadStaffDocumentRequest $request, Staff $staff): JsonResponse
    {
        $validated = $request->validated();

        $stored = $this->documents->store($validated['file'], $staff);

        $document = $staff->documents()->create([
            'uploaded_by' => $request->user()->id,
            'title' => $validated['title'],
            'category' => $validated['category'],
            'original_name' => $stored['original_name'],
            'path' => $stored['path'],
            'mime_type' => $stored['mime_type'],
            'size' => $stored['size'],
        ]);

        $this->events->record(SecurityEventService::STAFF_DOCUMENT_UPLOADED, $request->user(), 'info', [
            'staff_id' => $staff->id,
            'staff_number' => $staff->staff_number,
            'document_id' => $document->id,
            'title' => $document->title,
            'category' => $document->category,
        ], $request);

        return response()->json([
            'message' => 'Document uploaded successfully.',
            'document' => $document->load('uploadedBy:id,name'),
        ], 201);
    }

    /**
     * Stream a confidential staff document download.
     */
    public function download(Request $request, StaffDocument $document): Response
    {
        $this->authorize('view', $document);

        abort_unless($this->storage->exists($document->path), 404, 'File not found.');

        $staff = $document->staff;

        $this->events->record(SecurityEventService::STAFF_DOCUMENT_DOWNLOADED, $request->user(), 'info', [
            'staff_id' => $staff?->id,
            'document_id' => $document->id,
            'title' => $document->title,
        ], $request);

        return $this->storage->download($document->path, $document->original_name);
    }

    /**
     * Delete a confidential staff document.
     */
    public function destroy(Request $request, StaffDocument $document): JsonResponse
    {
        $this->authorize('delete', $document);

        $staff = $document->staff;
        $title = $document->title;

        $this->storage->delete($document->path);
        $document->delete();

        $this->events->record(SecurityEventService::STAFF_DOCUMENT_DELETED, $request->user(), 'warning', [
            'staff_id' => $staff?->id,
            'document_id' => $document->id,
            'title' => $title,
        ], $request);

        return response()->json([
            'message' => 'Document deleted successfully.',
        ]);
    }
}
