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
        $complianceStatus = $this->normalizeComplianceStatus($validated['complianceStatus']);
        $renewalStatus = $validated['renewalStatus']
            ?? $this->mapRenewalStatus($validated['renewalEligibility'] ?? null, $scholar->renewal_status);
        $scholarshipStatus = $validated['scholarshipStatus'] ?? $this->scholarshipStatusForRenewal($renewalStatus, $scholar->scholarship_status);
        $riskLevel = $this->normalizeRiskLevel($validated['riskLevel'] ?? $this->riskLabelForCompliance($complianceStatus));
        $officerNotes = $validated['officerNotes'] ?? $validated['recommendedAction'] ?? $this->recommendedActionForCompliance($complianceStatus);

        $scholar->update([
            'compliance_status' => $complianceStatus,
            'renewal_status' => $renewalStatus,
            'scholarship_status' => $scholarshipStatus,
            'recommended_action' => $officerNotes,
            'risk_label' => $riskLevel,
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
    private function mapRenewalStatus(?string $renewalEligibility, ?string $currentStatus = null): string
    {
        return match ($renewalEligibility) {
            'Eligible for Renewal' => 'Pending Renewal',
            'Under Evaluation' => 'Under Renewal Review',
            'Needs Review' => 'Pending Renewal',
            'Renewal Denied' => 'Suspended',
            default => $this->normalizeRenewalStatus($currentStatus ?: 'Active Scholar'),
        };
    }

    /**
     * Normalize old and new compliance labels.
     */
    private function normalizeComplianceStatus(string $complianceStatus): string
    {
        return match ($complianceStatus) {
            'Complete' => 'Compliant',
            'Pending Review', 'Missing Requirements' => 'Incomplete',
            default => $complianceStatus,
        };
    }

    /**
     * Normalize old and new renewal labels.
     */
    private function normalizeRenewalStatus(string $renewalStatus): string
    {
        return match ($renewalStatus) {
            'Active' => 'Active Scholar',
            'Renewal Pending' => 'Pending Renewal',
            'Under Evaluation', 'Under Review' => 'Under Renewal Review',
            default => $renewalStatus,
        };
    }

    /**
     * Determine the scholar status that should be visible to monitoring.
     */
    private function scholarshipStatusForRenewal(string $renewalStatus, ?string $currentStatus): string
    {
        if (in_array($renewalStatus, ['Probation', 'Suspended'], true)) {
            return $renewalStatus;
        }

        return $this->normalizeRenewalStatus($currentStatus ?: 'Active Scholar');
    }

    /**
     * Normalize old risk labels to the monitoring labels.
     */
    private function normalizeRiskLevel(string $riskLevel): string
    {
        return match ($riskLevel) {
            'Stable' => 'Low Risk',
            'Borderline' => 'Medium Risk',
            'At Risk', 'Critical' => 'High Risk',
            default => $riskLevel,
        };
    }

    /**
     * Map compliance status to a risk label.
     */
    private function riskLabelForCompliance(string $complianceStatus): string
    {
        return match ($complianceStatus) {
            'Compliant' => 'Low Risk',
            'Late Submission', 'Incomplete' => 'Medium Risk',
            'Non-Compliant' => 'High Risk',
            default => 'Medium Risk',
        };
    }

    /**
     * Map compliance status to a risk explanation.
     */
    private function riskReasonForCompliance(string $complianceStatus, Scholar $scholar): string
    {
        return match ($complianceStatus) {
            'Non-Compliant' => 'Scholar is not compliant with the current monitoring cycle.',
            'Incomplete' => 'One or more required documents are still outstanding.',
            'Late Submission' => 'Scholar submitted requirements after the expected deadline.',
            default => 'Scholar remains in good standing.',
        };
    }

    /**
     * Map compliance status to a completion percentage.
     */
    private function complianceRateForStatus(string $complianceStatus): int
    {
        return match ($complianceStatus) {
            'Compliant' => 100,
            'Late Submission' => 75,
            'Incomplete' => 55,
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
            'Compliant' => 'Continue normal scholarship monitoring.',
            'Late Submission' => 'Monitor the scholar for repeated late submissions.',
            'Incomplete' => 'Request the missing requirements from the scholar.',
            'Non-Compliant' => 'Recommend suspension or escalation for scholarship review.',
            default => 'Monitor the scholar closely.',
        };
    }
}
