<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Scholar;
use App\Models\ScholarshipProgram;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RiskDetectionController extends Controller
{
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
        $riskRows = $scholars
            ->map(fn (Scholar $scholar): array => $this->riskRow(
                $scholar,
                (float) $maintainingGrades->get($scholar->scholarship_program_id),
            ))
            ->values()
            ->all();
        $riskSummary = collect(['Stable', 'Borderline', 'At Risk'])
            ->map(fn (string $label): array => [
                'label' => $label,
                'count' => collect($riskRows)->where('riskLabel', $label)->count(),
            ])
            ->all();
        $totalScholars = max(count($riskRows), 1);
        $forecasts = collect($riskSummary)
            ->map(fn (array $item): array => [
                'label' => $item['label'],
                'value' => (int) round(($item['count'] / $totalScholars) * 100),
            ])
            ->all();

        return response()->json([
            'riskRows' => $riskRows,
            'riskSummary' => $riskSummary,
            'forecasts' => $forecasts,
        ]);
    }

    /**
     * Build one risk table row.
     *
     * @return array<string, mixed>
     */
    private function riskRow(Scholar $scholar, float $maintainingGrade): array
    {
        $riskLabel = $this->displayRiskLabel($scholar, $maintainingGrade);

        return [
            'id' => $scholar->id,
            'scholarId' => $scholar->scholar_id,
            'userId' => $scholar->user_id,
            'programId' => $scholar->scholarship_program_id,
            'name' => $scholar->name,
            'program' => $scholar->program,
            'gpa' => $scholar->gpa,
            'maintainingGrade' => $maintainingGrade,
            'riskLabel' => $riskLabel,
            'riskReason' => $this->riskReasonFor($scholar, $riskLabel, $maintainingGrade),
            'recommendedAction' => $scholar->recommended_action ?: $this->recommendedActionFor($riskLabel),
            'updatedAt' => $scholar->updated_at?->toISOString(),
        ];
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

    private function displayRiskLabel(Scholar $scholar, float $maintainingGrade): string
    {
        $gpa = (float) $scholar->gpa;

        if ($gpa <= 0 || $gpa < $maintainingGrade) {
            return 'At Risk';
        }

        return $gpa < ($maintainingGrade + 5) ? 'Borderline' : 'Stable';
    }

    private function riskReasonFor(Scholar $scholar, string $riskLabel, float $maintainingGrade): string
    {
        if ((float) $scholar->gpa <= 0) {
            return "No GPA recorded. Maintaining grade is {$maintainingGrade}.";
        }

        return match ($riskLabel) {
            'At Risk' => "GPA is below the maintaining grade of {$maintainingGrade}.",
            'Borderline' => "GPA is close to the maintaining grade of {$maintainingGrade}.",
            default => "GPA meets the maintaining grade of {$maintainingGrade}.",
        };
    }

    private function recommendedActionFor(string $riskLabel): string
    {
        return match ($riskLabel) {
            'At Risk' => 'Schedule advising and monitor the next GPA submission',
            'Borderline' => 'Send reminder and monitor next GPA submission',
            default => 'Continue normal monitoring',
        };
    }
}
