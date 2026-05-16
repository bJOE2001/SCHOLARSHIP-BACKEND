<?php

namespace Database\Seeders;

use App\Models\Scholar;
use App\Models\ScholarshipApplication;
use Illuminate\Database\Seeder;

class ScholarSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $applications = ScholarshipApplication::query()
            ->with(['applicant', 'program', 'documents'])
            ->whereIn('status', ['Accepted', 'Enrollment Verified', 'Active Scholar', 'Renewal Pending', 'Renewed'])
            ->get();

        foreach ($applications as $application) {
            $applicant = $application->applicant;
            $program = $application->program;

            if ($applicant === null || $program === null) {
                continue;
            }

            $submissions = $application->documents
                ->map(fn ($document) => [
                    'requirement' => $document->name,
                    'status' => $document->status,
                ])
                ->values()
                ->all();

            Scholar::updateOrCreate(
                [
                    'scholarship_application_id' => $application->id,
                ],
                [
                    'user_id' => $applicant->id,
                    'scholarship_program_id' => $program->id,
                    'scholar_id' => 'SCH-'.str_pad((string) $application->id, 6, '0', STR_PAD_LEFT),
                    'name' => $applicant->name,
                    'avatar' => $applicant->avatar,
                    'program' => $program->name,
                    'course' => $applicant->course,
                    'year_level' => $applicant->year_level,
                    'school' => $applicant->school_name ?: $applicant->campus,
                    'gender' => $applicant->gender,
                    'birthdate' => $applicant->birth_date,
                    'address' => $applicant->address,
                    'contact_number' => $applicant->contact_number,
                    'email' => $applicant->email,
                    'gpa' => $applicant->gpa,
                    'enrollment_status' => $applicant->enrollment_status,
                    'academic_year' => $applicant->academic_year,
                    'semester' => $applicant->semester,
                    'scholarship_status' => 'Active',
                    'renewal_status' => $this->renewalStatusForApplication($application->status),
                    'date_approved' => now()->subWeeks(2),
                    'duration' => '1 Academic Year',
                    'compliance_status' => $this->complianceStatusForApplication($application->status),
                    'compliance_rate' => $this->complianceRateForStatus($application->status),
                    'risk_label' => $this->riskLabelForApplication($application->status),
                    'risk_reason' => $this->riskReasonForApplication($application->status),
                    'recommended_action' => $this->recommendedActionForApplication($application->status),
                    'submissions' => $submissions,
                ],
            );
        }
    }

    private function renewalStatusForApplication(string $status): string
    {
        return match ($status) {
            'Renewed' => 'Renewed',
            'Renewal Pending' => 'Renewal Pending',
            'Enrollment Verified' => 'Under Evaluation',
            default => 'Active',
        };
    }

    private function complianceStatusForApplication(string $status): string
    {
        return match ($status) {
            'Accepted', 'Enrollment Verified', 'Active Scholar', 'Renewed' => 'Complete',
            'Renewal Pending' => 'Pending Review',
            'Needs Revision' => 'Missing Requirements',
            default => 'Complete',
        };
    }

    private function complianceRateForStatus(string $status): int
    {
        return match ($status) {
            'Accepted' => 95,
            'Enrollment Verified' => 100,
            'Active Scholar' => 100,
            'Renewed' => 100,
            'Renewal Pending' => 80,
            default => 75,
        };
    }

    private function riskLabelForApplication(string $status): string
    {
        return match ($status) {
            'Renewal Pending' => 'Borderline',
            'Needs Revision' => 'At Risk',
            default => 'Stable',
        };
    }

    private function riskReasonForApplication(string $status): string
    {
        return match ($status) {
            'Renewal Pending' => 'Scholar is approaching the renewal window.',
            'Needs Revision' => 'Scholar still needs to submit missing documents.',
            default => 'Scholar remains in good standing.',
        };
    }

    private function recommendedActionForApplication(string $status): string
    {
        return match ($status) {
            'Renewal Pending' => 'Send renewal reminders and monitor compliance.',
            'Needs Revision' => 'Request the missing requirements.',
            default => 'Continue normal monitoring.',
        };
    }
}
