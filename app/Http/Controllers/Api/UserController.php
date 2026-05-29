<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\SyncUserProgramsRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UpdateUserStatusRequest;
use App\Http\Resources\UserResource;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    private const DEFAULT_OFFICER_PASSWORD = 'password';

    /**
     * List users for the head officer system users interface.
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $search = trim((string) $request->query('search', ''));
        $role = $request->query('role');
        $status = $request->query('status');

        $users = User::query()
            ->when($role !== null && $role !== '', fn ($query) => $query->where('role', $role))
            ->when($status !== null && $status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('role', 'like', "%{$search}%")
                        ->orWhere('department', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get();

        return response()->json([
            'users' => UserResource::collection($users),
        ]);
    }

    /**
     * Store a new user.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = User::create($this->mapUserAttributes($validated, true));

        $this->syncAssignedPrograms($user, $this->programIdsFromPayload($validated));

        return response()->json([
            'user' => new UserResource($user),
        ], 201);
    }

    /**
     * Show one user.
     */
    public function show(User $user): JsonResponse
    {
        abort_unless(request()->user()?->isSuperAdmin(), 403);

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Update one user.
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();
        $user->fill($this->mapUserAttributes($validated));
        $user->save();

        $this->syncAssignedPrograms($user, $this->programIdsFromPayload($validated));

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Update a user's active status.
     */
    public function updateStatus(UpdateUserStatusRequest $request, User $user): JsonResponse
    {
        $user->update([
            'status' => $request->validated()['status'],
        ]);

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Assign scholarship programs to one user.
     */
    public function syncPrograms(SyncUserProgramsRequest $request, User $user): JsonResponse
    {
        $this->syncAssignedPrograms($user, $request->validated()['programIds']);

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Convert the incoming user payload into database attributes.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function mapUserAttributes(array $validated, bool $isCreation = false): array
    {
        $attributeMap = [
            'name' => 'name',
            'email' => 'email',
            'role' => 'role',
            'status' => 'status',
            'department' => 'department',
            'studentId' => 'student_id',
            'birthDate' => 'birth_date',
            'gender' => 'gender',
            'civilStatus' => 'civil_status',
            'citizenship' => 'citizenship',
            'address' => 'address',
            'barangay' => 'barangay',
            'city' => 'city',
            'province' => 'province',
            'contactNumber' => 'contact_number',
            'campus' => 'campus',
            'schoolName' => 'school_name',
            'course' => 'course',
            'yearLevel' => 'year_level',
            'semester' => 'semester',
            'academicYear' => 'academic_year',
            'gpa' => 'gpa',
            'familyIncome' => 'family_income',
            'enrollmentStatus' => 'enrollment_status',
            'academicAwards' => 'academic_awards',
            'fatherName' => 'father_name',
            'motherName' => 'mother_name',
            'guardianName' => 'guardian_name',
            'parentOccupation' => 'parent_occupation',
            'monthlyIncome' => 'monthly_income',
            'siblings' => 'siblings',
            'studyingSiblings' => 'studying_siblings',
            'incomeBracket' => 'income_bracket',
        ];

        $attributes = [];

        foreach ($attributeMap as $inputKey => $databaseColumn) {
            if (array_key_exists($inputKey, $validated)) {
                $attributes[$databaseColumn] = $validated[$inputKey];
            }
        }

        if ($isCreation) {
            $role = $attributes['role'] ?? 'student';

            $attributes['password'] = in_array($role, ['head_officer', 'officer'], true)
                ? self::DEFAULT_OFFICER_PASSWORD
                : 'password';
            $attributes['force_password_change'] = in_array($role, ['head_officer', 'officer'], true);
            $attributes['email_verified_at'] = now();
            $attributes['status'] = $attributes['status'] ?? 'Active';
            $attributes['role'] = $role;
        }

        return $attributes;
    }

    /**
     * Normalize program ids for assignment storage.
     *
     * @param  array<int, mixed>  $programIds
     * @return array<int, int>
     */
    private function normalizeProgramIds(array $programIds): array
    {
        return array_values(array_unique(array_map(
            static fn (mixed $programId): int => (int) $programId,
            $programIds,
        )));
    }

    /**
     * Get program ids from either supported API key.
     *
     * @param  array<string, mixed>  $validated
     * @return array<int, mixed>|null
     */
    private function programIdsFromPayload(array $validated): ?array
    {
        return $validated['programIds'] ?? $validated['assignedProgramIds'] ?? null;
    }

    /**
     * @param  array<int, mixed>|null  $programIds
     */
    private function syncAssignedPrograms(User $user, ?array $programIds): void
    {
        if ($programIds === null) {
            return;
        }

        $normalizedProgramIds = $this->normalizeProgramIds($programIds);
        $this->ensureCompatibleProgramGradePolicy($normalizedProgramIds);

        $user->assignedPrograms()->sync($normalizedProgramIds);
        $user->unsetRelation('assignedPrograms');
    }

    /**
     * Officers cannot be assigned to a mix of programs with and without a maintaining grade.
     *
     * @param  array<int, int>  $programIds
     */
    private function ensureCompatibleProgramGradePolicy(array $programIds): void
    {
        if (count($programIds) < 2) {
            return;
        }

        $gradePolicyCount = ScholarshipProgram::query()
            ->whereIn('id', $programIds)
            ->selectRaw('CASE WHEN maintaining_grade IS NULL OR maintaining_grade <= 0 THEN 0 ELSE 1 END as has_maintaining_grade')
            ->distinct()
            ->pluck('has_maintaining_grade')
            ->count();

        if ($gradePolicyCount > 1) {
            throw ValidationException::withMessages([
                'programIds' => ['Assign programs that all either require a maintaining grade or all do not require one.'],
            ]);
        }
    }
}
