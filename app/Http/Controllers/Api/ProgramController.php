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
            ->when($publishedOnly || $currentUser === null, function ($query): void {
                $query->whereIn('status', ['Open', 'Closing Soon']);
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
        $program = ScholarshipProgram::create($this->mapProgramAttributes($request->validated(), true));

        return response()->json([
            'program' => new ScholarshipProgramResource($program),
        ], 201);
    }

    /**
     * Show one scholarship program.
     */
    public function show(ScholarshipProgram $program): JsonResponse
    {
        return response()->json([
            'program' => new ScholarshipProgramResource($program),
        ]);
    }

    /**
     * Update a scholarship program.
     */
    public function update(UpdateProgramRequest $request, ScholarshipProgram $program): JsonResponse
    {
        $program->fill($this->mapProgramAttributes($request->validated()));

        if ($program->status === 'Open' && $program->published_at === null) {
            $program->published_at = now();
        }

        $program->save();

        return response()->json([
            'program' => new ScholarshipProgramResource($program),
        ]);
    }

    /**
     * Publish a scholarship program.
     */
    public function publish(ScholarshipProgram $program): JsonResponse
    {
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

        $program->update([
            'assigned_admin_ids' => array_values(array_unique(array_map(
                static fn (mixed $userId): int => (int) $userId,
                $assignedAdminIds,
            ))),
        ]);

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
            'scoringCriteria' => 'scoring_criteria',
            'renewalRules' => 'renewal_rules',
        ];

        $attributes = [];

        foreach ($attributeMap as $inputKey => $databaseColumn) {
            if (array_key_exists($inputKey, $validated)) {
                $attributes[$databaseColumn] = $validated[$inputKey];
            }
        }

        $assignedAdminIds = $validated['assignedAdminIds']
            ?? $validated['assignedOfficerIds']
            ?? null;

        if ($assignedAdminIds !== null) {
            $attributes['assigned_admin_ids'] = array_values(array_unique(array_map(
                static fn (mixed $userId): int => (int) $userId,
                $assignedAdminIds,
            )));
        }

        if ($isCreation) {
            $attributes['used_slots'] = $attributes['used_slots'] ?? 0;
            $attributes['assigned_admin_ids'] = $attributes['assigned_admin_ids'] ?? [];

            if (($attributes['status'] ?? 'Closed') === 'Open') {
                $attributes['published_at'] = now();
            }
        }

        return $attributes;
    }
}
