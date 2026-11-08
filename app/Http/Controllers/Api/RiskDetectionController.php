<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Scholar;
use App\Models\ScholarshipProgram;
use App\Services\ScholarRiskService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RiskDetectionController extends Controller
{
    public function __construct(private readonly ScholarRiskService $scholarRiskService) {}

    /**
     * Return risk-detection rows, summary tiles, and forecast percentages.
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isOfficer(), 403);

        $programIds = $this->visibleProgramIds($request);
        $maintainingGrades = ScholarshipProgram::query()
            ->whereNotNull('maintaining_grade')
            ->when($programIds !== null, fn (Builder $query) => $query->whereIn('id', $programIds))
            ->pluck('maintaining_grade', 'id')
            ->map(fn (mixed $grade): float => (float) $grade);

        abort_if($maintainingGrades->isEmpty(), 403);

        $scholars = Scholar::query()
            ->when($programIds !== null, fn (Builder $query) => $query->whereIn('scholarship_program_id', $programIds))
            ->whereIn('scholarship_program_id', $maintainingGrades->keys())
            ->orderBy('name')
            ->get();

        $riskEvaluation = $this->scholarRiskService->evaluate($scholars, $maintainingGrades);

        return response()->json($riskEvaluation);
    }

    /**
     * Return scoped program ids, or null for head officer/all-access users.
     *
     * @return array<int, int>|null
     */
    private function visibleProgramIds(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null || $user->isSuperAdmin()) {
            return null;
        }

        if ($user->isOfficer()) {
            return array_values(array_map('intval', $user->assigned_program_ids ?? []));
        }

        return [];
    }
}
