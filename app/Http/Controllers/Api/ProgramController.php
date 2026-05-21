<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProgramRequest;
use App\Http\Requests\UpdateProgramRequest;
use App\Http\Resources\ScholarshipProgramResource;
use App\Models\ScholarshipProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgramController extends Controller
{
    /**
     * List scholarship programs.
     */
    public function index(Request $request): JsonResponse
    {
        $publishedOnly = $request->boolean('published');
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');
        $currentUser = $request->user('sanctum');

        $programs = ScholarshipProgram::query()
            ->with('assignedOfficers:id')
            ->when($publishedOnly || $currentUser === null, function ($query): void {
                $query->whereIn('status', ['Open', 'Closing Soon']);
            })
            ->when($currentUser?->isOfficer() && ! $currentUser?->isSuperAdmin(), function ($query) use ($currentUser): void {
                $programIds = $this->assignedProgramIds($currentUser);

                $programIds === []
                    ? $query->whereRaw('1 = 0')
                    : $query->whereIn('id', $programIds);
            })
            ->when($status !== null && $status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('provider', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhere('type', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get();

        return response()->json([
            'programs' => ScholarshipProgramResource::collection($programs),
        ]);
    }

    /**
     * Store a scholarship program.
     */
    public function store(StoreProgramRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $program = ScholarshipProgram::create($this->mapProgramAttributes($validated, true));

        $this->syncAssignedOfficers($program, $this->assignedOfficerIdsFromPayload($validated) ?? []);

        return response()->json([
            'program' => new ScholarshipProgramResource($program),
        ], 201);
    }

    /**
     * Show one scholarship program.
     */
    public function show(ScholarshipProgram $program): JsonResponse
    {
        $currentUser = request()->user('sanctum');

        abort_unless($this->canAccessProgram($currentUser, $program), 403);

        return response()->json([
            'program' => new ScholarshipProgramResource($program),
        ]);
    }

    /**
     * Update a scholarship program.
     */
    public function update(UpdateProgramRequest $request, ScholarshipProgram $program): JsonResponse
    {
        $validated = $request->validated();
        $program->fill($this->mapProgramAttributes($validated));

        if ($program->status === 'Open' && $program->published_at === null) {
            $program->published_at = now();
        }

        $program->save();
        $this->syncAssignedOfficers($program, $this->assignedOfficerIdsFromPayload($validated));

        return response()->json([
            'program' => new ScholarshipProgramResource($program),
        ]);
    }

    /**
     * Publish a scholarship program.
     */
    public function publish(ScholarshipProgram $program): JsonResponse
    {
        abort_unless(request()->user()?->isSuperAdmin(), 403);

        $program->update([
            'status' => 'Open',
            'published_at' => now(),
        ]);

        return response()->json([
            'program' => new ScholarshipProgramResource($program),
        ]);
    }

    /**
     * Assign program access to users.
     */
    public function assignAdmins(Request $request, ScholarshipProgram $program): JsonResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $validated = $request->validate([
            'officerId' => ['nullable', 'integer', 'exists:users,id'],
            'adminIds' => ['nullable', 'array'],
            'adminIds.*' => ['integer', 'exists:users,id'],
            'assignedOfficerIds' => ['nullable', 'array'],
            'assignedOfficerIds.*' => ['integer', 'exists:users,id'],
            'assignedAdminIds' => ['nullable', 'array'],
            'assignedAdminIds.*' => ['integer', 'exists:users,id'],
        ]);

        $assignedAdminIds = $validated['assignedAdminIds']
            ?? $validated['assignedOfficerIds']
            ?? $validated['adminIds']
            ?? (isset($validated['officerId']) ? [$validated['officerId']] : []);

        $this->syncAssignedOfficers($program, $assignedAdminIds);

        return response()->json([
            'program' => new ScholarshipProgramResource($program),
        ]);
    }

    /**
     * Convert program payloads into database fields.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function mapProgramAttributes(array $validated, bool $isCreation = false): array
    {
        $attributeMap = [
            'name' => 'name',
            'provider' => 'provider',
            'category' => 'category',
            'type' => 'type',
            'description' => 'description',
            'eligibilitySummary' => 'eligibility_summary',
            'status' => 'status',
            'slots' => 'slots',
            'usedSlots' => 'used_slots',
            'budget' => 'budget',
            'schedule' => 'schedule',
            'eligibility' => 'eligibility',
            'requirements' => 'requirements',
            'requirementRules' => 'requirement_rules',
            'scoringCriteria' => 'scoring_criteria',
            'renewalRules' => 'renewal_rules',
        ];

        $attributes = [];

        foreach ($attributeMap as $inputKey => $databaseColumn) {
            if (array_key_exists($inputKey, $validated)) {
                $attributes[$databaseColumn] = $validated[$inputKey];
            }
        }

        if (array_key_exists('requirements', $validated) && ! array_key_exists('requirementRules', $validated)) {
            $attributes['requirement_rules'] = ScholarshipProgram::defaultRequirementRules($validated['requirements']);
        }

        if ($isCreation) {
            $attributes['used_slots'] = $attributes['used_slots'] ?? 0;

            if (($attributes['status'] ?? 'Closed') === 'Open') {
                $attributes['published_at'] = now();
            }
        }

        return $attributes;
    }

    /**
     * Check whether a user can view one program.
     */
    private function canAccessProgram($user, ScholarshipProgram $program): bool
    {
        if ($user === null) {
            return in_array($program->status, ['Open', 'Closing Soon'], true);
        }

        if ($user->isSuperAdmin() || $user->isStudent()) {
            return true;
        }

        if ($user->isOfficer()) {
            $programIds = $this->assignedProgramIds($user);

            return in_array((int) $program->id, $programIds, true);
        }

        return false;
    }

    /**
     * @return array<int, int>
     */
    private function assignedProgramIds($user): array
    {
        return $user->assignedPrograms()
            ->pluck('scholarship_programs.id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<int, mixed>|null
     */
    private function assignedOfficerIdsFromPayload(array $validated): ?array
    {
        return $validated['assignedAdminIds'] ?? $validated['assignedOfficerIds'] ?? null;
    }

    /**
     * @param  array<int, mixed>|null  $userIds
     */
    private function syncAssignedOfficers(ScholarshipProgram $program, ?array $userIds): void
    {
        if ($userIds === null) {
            return;
        }

        $program->assignedOfficers()->sync($this->normalizeUserIds($userIds));
        $program->unsetRelation('assignedOfficers');
    }

    /**
     * @param  array<int, mixed>  $userIds
     * @return array<int, int>
     */
    private function normalizeUserIds(array $userIds): array
    {
        return array_values(array_unique(array_map(
            static fn (mixed $userId): int => (int) $userId,
            $userIds,
        )));
    }
}
