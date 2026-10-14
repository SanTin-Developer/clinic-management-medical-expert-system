<?php

namespace App\Services\Staff;

use App\Models\Staff;
use App\Models\StaffDocument;
use App\Models\StaffSalaryHistory;
use App\Models\User;
use App\Services\Auth\SecurityEventService;
use App\Services\Files\StaffDocumentService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffService
{
    public function __construct(
        private readonly SecurityEventService $events,
        private readonly StaffDocumentService $documents,
    ) {}

    /**
     * Generate the next staff number, e.g. STF-0001.
     */
    public function generateStaffNumber(): string
    {
        $last = Staff::withTrashed()
            ->where('staff_number', 'LIKE', 'STF-%')
            ->orderBy('id', 'desc')
            ->value('staff_number');

        if ($last && preg_match('/STF-(\d+)/', $last, $matches)) {
            $next = ((int) $matches[1]) + 1;
        } else {
            $count = Staff::withTrashed()->count();
            $next = $count + 1;
        }

        $candidate = sprintf('STF-%04d', $next);

        while (Staff::withTrashed()->where('staff_number', $candidate)->exists()) {
            $next++;
            $candidate = sprintf('STF-%04d', $next);
        }

        return $candidate;
    }

    /**
     * Create a new staff profile, optionally attaching an initial CV document.
     *
     * @param  array<string, mixed>  $data
     */
    public function createStaff(array $data, User $actor, Request $request, ?UploadedFile $cv = null): Staff
    {
        return DB::transaction(function () use ($data, $actor, $request, $cv) {
            if (empty($data['staff_number'])) {
                $data['staff_number'] = $this->generateStaffNumber();
            }

            $salaryAmount = $data['salary_amount'] ?? null;
            $payFrequency = $data['pay_frequency'] ?? null;
            $allowances = $data['allowances'] ?? null;

            // Strip compensation if actor cannot update salary
            if (! $actor->can('staff.salary.update')) {
                unset($data['salary_amount'], $data['pay_frequency'], $data['allowances']);
            }

            $staff = Staff::create($data);

            if ($salaryAmount !== null && $actor->can('staff.salary.update')) {
                StaffSalaryHistory::create([
                    'staff_id' => $staff->id,
                    'changed_by' => $actor->id,
                    'amount' => $salaryAmount,
                    'pay_frequency' => $payFrequency,
                    'allowances' => $allowances,
                    'effective_from' => $staff->hire_date ?? now()->toDateString(),
                    'note' => 'Initial salary assignment',
                ]);
            }

            $this->events->record(SecurityEventService::STAFF_CREATED, $actor, 'info', [
                'staff_id' => $staff->id,
                'staff_number' => $staff->staff_number,
                'name' => $staff->full_name,
                'job_title' => $staff->job_title,
                'department' => $staff->department,
            ], $request);

            if ($cv !== null) {
                try {
                    $stored = $this->documents->store($cv, $staff);
                } catch (ValidationException $e) {
                    throw ValidationException::withMessages([
                        'cv' => $e->errors()['file'] ?? ['The CV file could not be processed.'],
                    ]);
                }

                $document = $staff->documents()->create([
                    'uploaded_by' => $actor->id,
                    'title' => 'Curriculum Vitae',
                    'category' => StaffDocument::CATEGORY_CV,
                    'original_name' => $stored['original_name'],
                    'path' => $stored['path'],
                    'mime_type' => $stored['mime_type'],
                    'size' => $stored['size'],
                ]);

                $this->events->record(SecurityEventService::STAFF_DOCUMENT_UPLOADED, $actor, 'info', [
                    'staff_id' => $staff->id,
                    'staff_number' => $staff->staff_number,
                    'document_id' => $document->id,
                    'title' => $document->title,
                    'category' => $document->category,
                ], $request);
            }

            return $staff;
        });
    }

    /**
     * Update an existing staff profile.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateStaff(Staff $staff, array $data, User $actor, Request $request): Staff
    {
        return DB::transaction(function () use ($staff, $data, $actor, $request) {
            $salaryChanged = false;
            $oldSalary = $staff->salary_amount;
            $newSalary = $data['salary_amount'] ?? null;

            if (array_key_exists('salary_amount', $data) && $actor->can('staff.salary.update')) {
                if ((string) $oldSalary !== (string) $newSalary) {
                    $salaryChanged = true;
                }
            } else {
                unset($data['salary_amount'], $data['pay_frequency'], $data['allowances']);
            }

            $staff->update($data);

            if ($salaryChanged) {
                StaffSalaryHistory::create([
                    'staff_id' => $staff->id,
                    'changed_by' => $actor->id,
                    'amount' => $staff->salary_amount,
                    'pay_frequency' => $staff->pay_frequency,
                    'allowances' => $staff->allowances,
                    'effective_from' => now()->toDateString(),
                    'note' => $data['salary_note'] ?? 'Salary update via profile edit',
                ]);

                $this->events->record(SecurityEventService::STAFF_SALARY_UPDATED, $actor, 'info', [
                    'staff_id' => $staff->id,
                    'staff_number' => $staff->staff_number,
                    'old_amount' => $oldSalary,
                    'new_amount' => $staff->salary_amount,
                ], $request);
            }

            $this->events->record(SecurityEventService::STAFF_UPDATED, $actor, 'info', [
                'staff_id' => $staff->id,
                'staff_number' => $staff->staff_number,
                'changes' => array_keys($data),
            ], $request);

            return $staff->fresh();
        });
    }

    /**
     * Record a formal salary change with effective date and audit notes.
     *
     * @param  array{salary_amount: numeric, pay_frequency?: string, allowances?: numeric, effective_from?: string, note?: string}  $data
     */
    public function updateSalary(Staff $staff, array $data, User $actor, Request $request): StaffSalaryHistory
    {
        return DB::transaction(function () use ($staff, $data, $actor, $request) {
            $oldAmount = $staff->salary_amount;

            $staff->update([
                'salary_amount' => $data['salary_amount'],
                'pay_frequency' => $data['pay_frequency'] ?? $staff->pay_frequency,
                'allowances' => $data['allowances'] ?? $staff->allowances,
            ]);

            $history = StaffSalaryHistory::create([
                'staff_id' => $staff->id,
                'changed_by' => $actor->id,
                'amount' => $data['salary_amount'],
                'pay_frequency' => $data['pay_frequency'] ?? $staff->pay_frequency,
                'allowances' => $data['allowances'] ?? $staff->allowances,
                'effective_from' => $data['effective_from'] ?? now()->toDateString(),
                'note' => $data['note'] ?? null,
            ]);

            $this->events->record(SecurityEventService::STAFF_SALARY_UPDATED, $actor, 'info', [
                'staff_id' => $staff->id,
                'staff_number' => $staff->staff_number,
                'old_amount' => $oldAmount,
                'new_amount' => $data['salary_amount'],
                'effective_from' => $history->effective_from,
                'note' => $history->note,
            ], $request);

            return $history;
        });
    }

    /**
     * Link an existing User account to a Staff profile.
     * Enforces that a user cannot be linked to multiple staff members.
     */
    public function linkUser(Staff $staff, User $user, User $actor, Request $request): Staff
    {
        return DB::transaction(function () use ($staff, $user, $actor, $request) {
            // Check if user is already linked to another staff profile
            $existing = Staff::query()
                ->where('user_id', $user->id)
                ->whereKeyNot($staff->id)
                ->first();

            if ($existing !== null) {
                throw ValidationException::withMessages([
                    'user_id' => ["This user is already linked to staff profile {$existing->staff_number} ({$existing->full_name})."],
                ]);
            }

            // If another user was previously linked to this staff profile, clear that link
            $previousUserId = $staff->user_id;

            $staff->update(['user_id' => $user->id]);

            // Sync user's staff_id string if needed
            $user->update(['staff_id' => $staff->staff_number]);

            $this->events->record(SecurityEventService::STAFF_USER_LINKED, $actor, 'info', [
                'staff_id' => $staff->id,
                'staff_number' => $staff->staff_number,
                'user_id' => $user->id,
                'username' => $user->username,
                'previous_user_id' => $previousUserId,
            ], $request);

            return $staff->fresh(['user', 'supervisor']);
        });
    }

    /**
     * Unlink a User account from a Staff profile.
     */
    public function unlinkUser(Staff $staff, User $actor, Request $request): Staff
    {
        return DB::transaction(function () use ($staff, $actor, $request) {
            $userId = $staff->user_id;

            if ($userId !== null) {
                $linkedUser = User::find($userId);
                if ($linkedUser && $linkedUser->staff_id === $staff->staff_number) {
                    $linkedUser->update(['staff_id' => null]);
                }
            }

            $staff->update(['user_id' => null]);

            $this->events->record(SecurityEventService::STAFF_USER_UNLINKED, $actor, 'info', [
                'staff_id' => $staff->id,
                'staff_number' => $staff->staff_number,
                'unlinked_user_id' => $userId,
            ], $request);

            return $staff->fresh(['user', 'supervisor']);
        });
    }
}
