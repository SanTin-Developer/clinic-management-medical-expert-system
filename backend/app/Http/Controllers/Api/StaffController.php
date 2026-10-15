<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\LinkStaffUserRequest;
use App\Http\Requests\Staff\StoreStaffRequest;
use App\Http\Requests\Staff\UpdateStaffRequest;
use App\Http\Requests\Staff\UpdateStaffSalaryRequest;
use App\Models\Staff;
use App\Models\StaffDocument;
use App\Models\User;
use App\Services\Auth\SecurityEventService;
use App\Services\Staff\StaffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function __construct(
        private readonly StaffService $staffService,
        private readonly SecurityEventService $events,
    ) {}

    /**
     * Display a paginated list of staff members with filtering and search.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Staff::class);

        $query = Staff::query()
            ->with([
                'user:id,name,username,email,account_status',
                'supervisor:id,staff_number,first_name,last_name',
            ]);

        // Search by name, staff number, email, phone, job title
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('staff_number', 'ILIKE', "%{$search}%")
                    ->orWhere('first_name', 'ILIKE', "%{$search}%")
                    ->orWhere('last_name', 'ILIKE', "%{$search}%")
                    ->orWhere('email', 'ILIKE', "%{$search}%")
                    ->orWhere('phone', 'ILIKE', "%{$search}%")
                    ->orWhere('job_title', 'ILIKE', "%{$search}%");
            });
        }

        if ($department = $request->query('department')) {
            $query->where('department', $department);
        }

        if ($jobTitle = $request->query('job_title')) {
            $query->where('job_title', $jobTitle);
        }

        if ($status = $request->query('employment_status')) {
            $query->where('employment_status', $status);
        }

        if ($type = $request->query('employment_type')) {
            $query->where('employment_type', $type);
        }

        if ($hasAccount = $request->query('has_account')) {
            if ($hasAccount === 'true' || $hasAccount === '1' || $hasAccount === 'yes') {
                $query->whereNotNull('user_id');
            } elseif ($hasAccount === 'false' || $hasAccount === '0' || $hasAccount === 'no') {
                $query->whereNull('user_id');
            }
        }

        $sort = $request->query('sort', 'created_at');
        $direction = $request->query('direction', 'desc');
        $allowedSorts = ['staff_number', 'first_name', 'last_name', 'department', 'job_title', 'hire_date', 'employment_status', 'created_at'];

        if (in_array($sort, $allowedSorts, true)) {
            $query->orderBy($sort, $direction === 'asc' ? 'asc' : 'desc');
        } else {
            $query->latest();
        }

        $perPage = min((int) $request->query('per_page', 15), 100);
        $paginated = $query->paginate($perPage);

        $canViewSalary = $request->user()->can('staff.salary.view');

        // Transform results to redact salary if unauthorized
        $paginated->getCollection()->transform(function (Staff $staff) use ($canViewSalary) {
            if (! $canViewSalary) {
                $staff->makeHidden(['salary_amount', 'pay_frequency', 'allowances']);
            }

            return $staff;
        });

        $stats = [
            'total' => Staff::query()->count(),
            'employed' => Staff::query()->where('employment_status', 'employed')->count(),
            'probation' => Staff::query()->where('employment_status', 'probation')->count(),
            'on_leave' => Staff::query()->where('employment_status', 'on_leave')->count(),
            'with_account' => Staff::query()->whereNotNull('user_id')->count(),
            'this_month' => Staff::query()->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
        ];

        return response()->json(array_merge($paginated->toArray(), ['stats' => $stats]));
    }

    /**
     * Store a new staff profile.
     */
    public function store(StoreStaffRequest $request): JsonResponse
    {
        $data = $request->validated();
        $cv = $request->file('cv');
        $photo = $request->file('photo');
        unset($data['cv'], $data['photo']);

        $staff = $this->staffService->createStaff($data, $request->user(), $request, $cv, $photo);

        return response()->json([
            'message' => 'Staff profile created successfully.',
            'staff' => $this->staffPayload($staff->fresh(['user', 'supervisor']), $request->user()),
        ], 201);
    }

    /**
     * Display a specific staff member.
     */
    public function show(Request $request, Staff $staff): JsonResponse
    {
        $this->authorize('view', $staff);

        $canViewSalary = $request->user()->can('viewSalary', $staff);

        $relations = [
            'user:id,name,username,email,account_status,phone,email_verified_at,created_at',
            'supervisor:id,staff_number,first_name,last_name,job_title,department',
            'subordinates:id,staff_number,first_name,last_name,job_title,department',
        ];

        if ($canViewSalary) {
            $relations['salaryHistories.changedBy'] = fn ($q) => $q->select('id', 'name');
        }

        if ($request->user()->can('viewForStaff', [StaffDocument::class, $staff])) {
            $relations[] = 'documents.uploadedBy:id,name';
        }

        $staff->load($relations);

        return response()->json([
            'staff' => $this->staffPayload($staff, $request->user()),
        ]);
    }

    /**
     * Update an existing staff profile.
     */
    public function update(UpdateStaffRequest $request, Staff $staff): JsonResponse
    {
        $updated = $this->staffService->updateStaff($staff, $request->validated(), $request->user(), $request);

        return response()->json([
            'message' => 'Staff profile updated successfully.',
            'staff' => $this->staffPayload($updated->load(['user', 'supervisor']), $request->user()),
        ]);
    }

    /**
     * Delete a staff profile.
     */
    public function destroy(Request $request, Staff $staff): JsonResponse
    {
        $this->authorize('delete', $staff);

        $staffNumber = $staff->staff_number;
        $name = $staff->full_name;

        $staff->delete();

        $this->events->record(SecurityEventService::STAFF_DELETED, $request->user(), 'warning', [
            'staff_id' => $staff->id,
            'staff_number' => $staffNumber,
            'name' => $name,
        ], $request);

        return response()->json([
            'message' => 'Staff profile deleted successfully.',
        ]);
    }

    /**
     * Update staff compensation with an audit record.
     */
    public function updateSalary(UpdateStaffSalaryRequest $request, Staff $staff): JsonResponse
    {
        $history = $this->staffService->updateSalary($staff, $request->validated(), $request->user(), $request);

        return response()->json([
            'message' => 'Compensation updated successfully.',
            'staff' => $this->staffPayload($staff->fresh(['user', 'supervisor']), $request->user()),
            'history' => $history->load('changedBy:id,name'),
        ]);
    }

    /**
     * Link an existing User account to a Staff profile.
     */
    public function linkUser(LinkStaffUserRequest $request, Staff $staff): JsonResponse
    {
        $targetUser = User::findOrFail($request->validated()['user_id']);

        $updated = $this->staffService->linkUser($staff, $targetUser, $request->user(), $request);

        return response()->json([
            'message' => 'User account linked successfully.',
            'staff' => $this->staffPayload($updated, $request->user()),
        ]);
    }

    /**
     * Unlink a User account from a Staff profile.
     */
    public function unlinkUser(Request $request, Staff $staff): JsonResponse
    {
        $this->authorize('linkUser', $staff);

        $updated = $this->staffService->unlinkUser($staff, $request->user(), $request);

        return response()->json([
            'message' => 'User account unlinked successfully.',
            'staff' => $this->staffPayload($updated, $request->user()),
        ]);
    }

    /**
     * Get list of users available to be linked to a staff profile.
     */
    public function availableUsers(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Staff::class);

        $forStaffId = $request->query('staff_id');

        $users = User::query()
            ->where(function ($q) use ($forStaffId) {
                // User has no staff linked, OR is linked to current staff member
                $q->whereDoesntHave('staffProfile');

                if ($forStaffId) {
                    $q->orWhereHas('staffProfile', fn ($sq) => $sq->where('id', $forStaffId));
                }
            })
            ->select('id', 'name', 'username', 'email', 'account_status')
            ->orderBy('name')
            ->get();

        return response()->json(['users' => $users]);
    }

    /**
     * Get list of unique departments.
     */
    public function departments(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Staff::class);

        $departments = Staff::query()
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->distinct()
            ->orderBy('department')
            ->pluck('department');

        return response()->json(['departments' => $departments]);
    }

    /**
     * Get list of unique job titles.
     */
    public function jobTitles(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Staff::class);

        $titles = Staff::query()
            ->whereNotNull('job_title')
            ->where('job_title', '!=', '')
            ->distinct()
            ->orderBy('job_title')
            ->pluck('job_title');

        return response()->json(['job_titles' => $titles]);
    }

    /**
     * Format staff payload and protect salary data if user lacks permission.
     */
    private function staffPayload(Staff $staff, User $actor): array
    {
        $canViewSalary = $actor->can('viewSalary', $staff);

        $data = $staff->toArray();

        if (! $canViewSalary) {
            unset($data['salary_amount'], $data['pay_frequency'], $data['allowances'], $data['salary_histories']);
        }

        return $data;
    }
}
