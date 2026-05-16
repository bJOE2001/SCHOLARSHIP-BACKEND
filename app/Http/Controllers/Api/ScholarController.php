<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRequirementRequest;
use App\Http\Requests\UpdateScholarComplianceRequest;
use App\Http\Resources\ScholarResource;
use App\Models\Scholar;
use App\Models\ScholarshipNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScholarController extends Controller
{
    /**
     * List the current scholars.
     */
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $riskLabel = $request->query('riskLabel');
        $renewalStatus = $request->query('renewalStatus');

        $scholars = Scholar::query()
            ->when($riskLabel !== null && $riskLabel !== '', fn ($query) => $query->where('risk_label', $riskLabel))
            ->when($renewalStatus !== null && $renewalStatus !== '', fn ($query) => $query->where('renewal_status', $renewalStatus))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('program', 'like', "%{$search}%")
                        ->orWhere('scholar_id', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->get();

        return response()->json([
            'scholars' => ScholarResource::collection($scholars),
        ]);
    }

    /**
     * Show one scholar.
     */
    public function show(Scholar $scholar): JsonResponse
    {
        return response()->json([
            'scholar' => new ScholarResource($scholar),
        ]);
    }

    /**
     * Update a scholar's compliance record.
     */
    public function updateCompliance(UpdateScholarComplianceRequest $request, Scholar $scholar): JsonResponse
    {
        $validated = $request->validated();
        $complianceStatus = $validated['complianceStatus'];

        $scholar->update([
            'compliance_status' => $complianceStatus,
            'renewal_status' => $this->mapRenewalStatus($validated['renewalEligibility'] ?? null),
            'recommended_action' => $validated['recommendedAction'] ?? $this->recommendedActionForCompliance($complianceStatus),
            'risk_label' => $this->riskLabelForCompliance($complianceStatus),
            'risk_reason' => $this->riskReasonForCompliance($complianceStatus, $scholar),
            'compliance_rate' => $this->complianceRateForStatus($complianceStatus),
        ]);

        return response()->json([
            'scholar' => new ScholarResource($scholar->refresh()),
        ]);
    }

    /**
     * Send a requirement request to a scholar.
     */
    public function sendRequirementRequest(StoreRequirementRequest $request, Scholar $scholar): JsonResponse
    {
        $validated = $request->validated();
        $requirements = $validated['requirements'];
        $message = $validated['message'] ?? 'Please submit the requested requirements.';

        ScholarshipNotification::create([
            'user_id' => $scholar->user_id,
            'role' => $scholar->user?->role ?? 'student',
            'type' => 'task',
            'title' => 'Requirement Request',
            'message' => $message.' Requirements: '.implode(', ', $requirements),
            'notified_at' => now(),
            'payload' => [
                'scholarId' => $scholar->id,
                'requirements' => $requirements,
            ],
        ]);

        return response()->json([
            'message' => 'Requirement request sent.',
        ], 201);
    }

    /**
     * Map compliance decisions to renewal status values.
     */
    private function mapRenewalStatus(?string $renewalEligibility): string
    {
        return match ($renewalEligibility) {
            'Eligible for Renewal' => 'Renewal Pending',
            'Under Evaluation' => 'Under Evaluation',
            'Needs Review' => 'Renewal Pending',
            'Renewal Denied' => 'Terminated',
            default => 'Active',
        };
    }

    /**
     * Map compliance status to a risk label.
     */
    private function riskLabelForCompliance(string $complianceStatus): string
    {
        return match ($complianceStatus) {
            'Non-Compliant' => 'Critical',
            'Missing Requirements' => 'At Risk',
            'Pending Review' => 'Borderline',
            default => 'Stable',
        };
    }

    /**
     * Map compliance status to a risk explanation.
     */
    private function riskReasonForCompliance(string $complianceStatus, Scholar $scholar): string
    {
        return match ($complianceStatus) {
            'Non-Compliant' => 'Scholar is not compliant with the current monitoring cycle.',
            'Missing Requirements' => 'One or more required documents are still outstanding.',
            'Pending Review' => 'Compliance review is still in progress.',
            default => 'Scholar remains in good standing.',
        };
    }

    /**
     * Map compliance status to a completion percentage.
     */
    private function complianceRateForStatus(string $complianceStatus): int
    {
        return match ($complianceStatus) {
            'Complete' => 100,
            'Pending Review' => 75,
            'Missing Requirements' => 55,
            'Non-Compliant' => 30,
            default => 80,
        };
    }

    /**
     * Map compliance status to a recommended action.
     */
    private function recommendedActionForCompliance(string $complianceStatus): string
    {
        return match ($complianceStatus) {
            'Complete' => 'Continue normal scholarship monitoring.',
            'Pending Review' => 'Follow up after the next review cycle.',
            'Missing Requirements' => 'Request the missing requirements from the scholar.',
            'Non-Compliant' => 'Escalate for scholarship review.',
            default => 'Monitor the scholar closely.',
        };
    }
}
