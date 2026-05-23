<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Scholar;
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
        $scholars = Scholar::query()
            ->when($programIds !== null, fn (Builder $query) => $query->whereIn('scholarship_program_id', $programIds))
            ->orderBy('name')
            ->get();
        $riskRows = $scholars
            ->map(fn (Scholar $scholar): array => $this->riskRow($scholar))
            ->values()
            ->all();
        $riskSummary = collect(['Stable', 'Borderline', 'At Risk', 'Critical'])
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
    private function riskRow(Scholar $scholar): array
    {
        $riskLabel = $this->displayRiskLabel($scholar);

        return [
            'id' => $scholar->id,
            'scholarId' => $scholar->scholar_id,
            'userId' => $scholar->user_id,
            'programId' => $scholar->scholarship_program_id,
            'name' => $scholar->name,
            'program' => $scholar->program,
            'gpa' => $scholar->gpa,
            'complianceStatus' => $this->displayComplianceStatus($scholar->compliance_status),
            'riskLabel' => $riskLabel,
            'riskReason' => $scholar->risk_reason ?: $this->riskReasonFor($scholar, $riskLabel),
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

    private function displayRiskLabel(Scholar $scholar): string
    {
        $riskLabel = $scholar->risk_label ?: 'Stable';

        if (in_array($riskLabel, ['Stable', 'Borderline', 'At Risk', 'Critical'], true)) {
            return $riskLabel;
        }

        return match ($riskLabel) {
            'High Risk' => ($scholar->compliance_status === 'Non-Compliant' || (int) $scholar->compliance_rate < 40) ? 'Critical' : 'At Risk',
            'Medium Risk' => 'Borderline',
            default => 'Stable',
        };
    }

    private function displayComplianceStatus(?string $status): string
    {
        return match ($status) {
            'Compliant' => 'Complete',
            'Under Review' => 'Pending Review',
            'Incomplete' => 'Missing Requirements',
            default => $status ?: 'Pending Review',
        };
    }

    private function riskReasonFor(Scholar $scholar, string $riskLabel): string
    {
        if ((float) $scholar->gpa > 2.5) {
            return 'Low GPA and renewal eligibility risk';
        }

        return match ($riskLabel) {
            'Critical' => 'Non-compliant semester submissions',
            'At Risk' => 'Missing requirements detected',
            'Borderline' => 'GPA is close to renewal threshold',
            default => 'Complete requirements and strong GPA trend',
        };
    }

    private function recommendedActionFor(string $riskLabel): string
    {
        return match ($riskLabel) {
            'Critical' => 'Escalate for intervention review',
            'At Risk' => 'Request requirements and schedule advising',
            'Borderline' => 'Send reminder and monitor next submission',
            default => 'Continue normal monitoring',
        };
    }
}
