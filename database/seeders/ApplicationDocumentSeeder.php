<?php

namespace Database\Seeders;

use App\Models\ApplicationDocument;
use App\Models\ScholarshipApplication;
use Illuminate\Database\Seeder;

class ApplicationDocumentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $applications = ScholarshipApplication::query()->with('program')->get();

        foreach ($applications as $application) {
            $requirements = $application->program?->requirements ?? [];

            foreach ($requirements as $index => $requirement) {
                $status = match ($application->status) {
                    'Accepted', 'Active Scholar', 'Renewed' => 'Accepted',
                    'Needs Revision' => $index === 0 ? 'Rejected' : 'Missing',
                    'Submitted', 'Under Review' => $index === 0 ? 'Accepted' : 'Pending',
                    default => 'Missing',
                };

                ApplicationDocument::updateOrCreate(
                    [
                        'scholarship_application_id' => $application->id,
                        'name' => $requirement,
                    ],
                    [
                        'type' => 'PDF',
                        'path' => 'applications/'.$application->id.'/'.str($requirement)->slug().'.pdf',
                        'status' => $status,
                        'remarks' => match ($status) {
                            'Accepted' => 'Validated and accepted.',
                            'Rejected' => 'Please upload a clearer copy.',
                            'Pending' => 'Uploaded and awaiting validation.',
                            default => 'Waiting for upload.',
                        },
                        'uploaded_by_id' => $application->applicant_id,
                        'validated_by_id' => $status === 'Accepted' ? $application->reviewed_by_id : null,
                        'uploaded_at' => now()->subDays($index + 2),
                    ],
                );
            }

            $missingRequirements = collect($application->documents)
                ->filter(fn (ApplicationDocument $document) => in_array($document->status, ['Missing', 'Rejected'], true))
                ->pluck('name')
                ->unique()
                ->values()
                ->all();

            $application->update([
                'missing_requirements' => $missingRequirements,
            ]);
        }
    }
}
