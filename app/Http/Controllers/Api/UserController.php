<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\SyncUserProgramsRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UpdateUserStatusRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * List users for the admin interface.
     */
    public function index(Request $request): JsonResponse
    {
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
        $user = User::create($this->mapUserAttributes($request->validated(), true));

        return response()->json([
            'user' => new UserResource($user),
        ], 201);
    }

    /**
     * Show one user.
     */
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Update one user.
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $user->fill($this->mapUserAttributes($request->validated()));
        $user->save();

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
        $user->update([
            'assigned_program_ids' => array_values(array_unique(array_map(
                static fn (mixed $programId): int => (int) $programId,
                $request->validated()['programIds'],
            ))),
        ]);

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
            $attributes['password'] = 'password';
            $attributes['email_verified_at'] = now();
            $attributes['assigned_program_ids'] = $attributes['assigned_program_ids'] ?? [];
            $attributes['status'] = $attributes['status'] ?? 'Active';
            $attributes['role'] = $attributes['role'] ?? 'student';
        }

        return $attributes;
    }
}
