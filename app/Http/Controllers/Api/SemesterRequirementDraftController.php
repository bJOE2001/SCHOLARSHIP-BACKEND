<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSemesterRequirementDraftRequest;
use App\Http\Resources\SemesterRequirementDraftResource;
use App\Models\Scholar;
use App\Models\SemesterRequirementDraft;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SemesterRequirementDraftController extends Controller
{
    /**
     * Show the current user's semester requirement draft.
     */
    public function show(Request $request): JsonResponse
    {
        $draft = $this->draftForUser($request->user());

        return response()->json([
            'draft' => new SemesterRequirementDraftResource($draft),
        ]);
    }

    /**
     * Save the current user's semester requirement draft.
     */
    public function update(UpdateSemesterRequirementDraftRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $currentUser = $request->user();
        $scholar = $this->scholarFromPayload($currentUser, $validated);
        $applicationId = $validated['applicationId'] ?? $validated['scholarshipApplicationId'] ?? $scholar?->scholarship_application_id;
        $grades = $this->normalizeGradeRows($validated['grades'] ?? []);
        $status = $validated['status'] ?? 'Draft';

        $draft = SemesterRequirementDraft::updateOrCreate(
            ['user_id' => $currentUser?->id],
            [
                'scholar_id' => $scholar?->id,
                'scholarship_application_id' => $applicationId,
                'status' => $status,
                'grades' => $grades,
                'computed_average' => $validated['computedAverage'] ?? $this->computeAverage($grades),
                'submitted_at' => $status === 'Submitted' ? now() : null,
            ],
        );

        return response()->json([
            'draft' => new SemesterRequirementDraftResource($draft),
        ], $draft->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Delete the current user's semester requirement draft.
     */
    public function destroy(Request $request): Response
    {
        SemesterRequirementDraft::query()
            ->where('user_id', $request->user()?->id)
            ->delete();

        return response()->noContent();
    }

    /**
     * Return an existing draft, or a draft-shaped unsaved model.
     */
    private function draftForUser(?User $user): SemesterRequirementDraft
    {
        $existingDraft = SemesterRequirementDraft::query()
            ->where('user_id', $user?->id)
            ->first();

        if ($existingDraft !== null) {
            return $existingDraft;
        }

        $scholar = Scholar::query()->where('user_id', $user?->id)->latest()->first();

        return new SemesterRequirementDraft([
            'user_id' => $user?->id,
            'scholar_id' => $scholar?->id,
            'scholarship_application_id' => $scholar?->scholarship_application_id,
            'status' => 'Draft',
            'grades' => [],
        ]);
    }

    /**
     * Resolve and authorize a scholar from the draft payload.
     *
     * @param  array<string, mixed>  $validated
     */
    private function scholarFromPayload(?User $user, array $validated): ?Scholar
    {
        $scholarId = $validated['scholarId'] ?? null;
        $query = Scholar::query();

        if ($scholarId !== null) {
            $scholar = $query->findOrFail($scholarId);
        } else {
            $scholar = $query->where('user_id', $user?->id)->latest()->first();
        }

        if ($scholar !== null) {
            abort_unless($this->canAccessScholar($user, $scholar), 403);
        }

        return $scholar;
    }

    /**
     * Check whether a user can access one scholar record.
     */
    private function canAccessScholar(?User $user, Scholar $scholar): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isOfficer()) {
            return in_array((int) $scholar->scholarship_program_id, $this->assignedProgramIds($user), true);
        }

        return $scholar->user_id === $user->id;
    }

    /**
     * Return assigned scholarship program ids for an officer.
     *
     * @return array<int, int>
     */
    private function assignedProgramIds(User $user): array
    {
        return array_values(array_map('intval', $user->assigned_program_ids ?? []));
    }

    /**
     * Normalize grade rows into the same shape used by semester submissions.
     *
     * @param  array<int, array<string, mixed>>  $grades
     * @return array<int, array<string, mixed>>
     */
    private function normalizeGradeRows(array $grades): array
    {
        return collect($grades)
            ->filter(fn (array $grade): bool => ($grade['code'] ?? $grade['subjectCode'] ?? $grade['name'] ?? $grade['subjectName'] ?? $grade['grade'] ?? '') !== '')
            ->values()
            ->map(function (array $grade, int $index): array {
                $gradeValue = $grade['grade'] ?? null;

                return [
                    'id' => $grade['id'] ?? $index,
                    'code' => $grade['code'] ?? $grade['subjectCode'] ?? '',
                    'subjectCode' => $grade['subjectCode'] ?? $grade['code'] ?? '',
                    'name' => $grade['name'] ?? $grade['subjectName'] ?? '',
                    'subjectName' => $grade['subjectName'] ?? $grade['name'] ?? '',
                    'units' => (float) ($grade['units'] ?? 0),
                    'grade' => $gradeValue === '' || $gradeValue === null ? null : (float) $gradeValue,
                ];
            })
            ->all();
    }

    /**
     * Compute a weighted grade average.
     *
     * @param  array<int, array<string, mixed>>  $grades
     */
    private function computeAverage(array $grades): ?float
    {
        $totalUnits = collect($grades)->sum(fn (array $grade): float => (float) ($grade['units'] ?? 0));

        if ($totalUnits <= 0) {
            return null;
        }

        $weightedTotal = collect($grades)->sum(fn (array $grade): float => (float) ($grade['grade'] ?? 0) * (float) ($grade['units'] ?? 0));

        return round($weightedTotal / $totalUnits, 2);
    }
}
