<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->renameRequirementLists('Certificate of Registration', 'Certificate of Ratings');
        $this->renameDocumentLabels('Certificate of Registration', 'Certificate of Ratings');
        $this->renameSubmissionLabels('Certificate of Registration / COR', 'Certificate of Ratings / COR');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->renameRequirementLists('Certificate of Ratings', 'Certificate of Registration');
        $this->renameDocumentLabels('Certificate of Ratings', 'Certificate of Registration');
        $this->renameSubmissionLabels('Certificate of Ratings / COR', 'Certificate of Registration / COR');
    }

    private function renameRequirementLists(string $from, string $to): void
    {
        $this->renameJsonListValues('scholarship_programs', 'requirements', $from, $to);
        $this->renameJsonListValues('scholarship_applications', 'missing_requirements', $from, $to);
    }

    private function renameDocumentLabels(string $from, string $to): void
    {
        DB::table('application_documents')
            ->where('name', $from)
            ->update(['name' => $to]);

        DB::table('application_documents')
            ->where('name', "{$from} / COR")
            ->update(['name' => "{$to} / COR"]);
    }

    private function renameSubmissionLabels(string $from, string $to): void
    {
        $this->renameSubmissionListValues('scholars', $from, $to);
        $this->renameSubmissionListValues('scholar_compliance_submissions', $from, $to);
    }

    private function renameJsonListValues(string $table, string $column, string $from, string $to): void
    {
        DB::table($table)
            ->select(['id', $column])
            ->orderBy('id')
            ->get()
            ->each(function (object $record) use ($table, $column, $from, $to): void {
                $values = $this->decodeJsonList($record->{$column});

                if ($values === null) {
                    return;
                }

                $updatedValues = array_map(
                    static fn (mixed $value): mixed => $value === $from ? $to : $value,
                    $values,
                );

                if ($updatedValues === $values) {
                    return;
                }

                DB::table($table)
                    ->where('id', $record->id)
                    ->update([$column => json_encode($updatedValues, JSON_THROW_ON_ERROR)]);
            });
    }

    private function renameSubmissionListValues(string $table, string $from, string $to): void
    {
        DB::table($table)
            ->select(['id', 'submissions'])
            ->orderBy('id')
            ->get()
            ->each(function (object $record) use ($table, $from, $to): void {
                $submissions = $this->decodeJsonList($record->submissions);

                if ($submissions === null) {
                    return;
                }

                $updatedSubmissions = array_map(function (mixed $submission) use ($from, $to): mixed {
                    if (! is_array($submission)) {
                        return $submission;
                    }

                    foreach (['requirement', 'name'] as $labelKey) {
                        if (($submission[$labelKey] ?? null) === $from) {
                            $submission[$labelKey] = $to;
                        }
                    }

                    return $submission;
                }, $submissions);

                if ($updatedSubmissions === $submissions) {
                    return;
                }

                DB::table($table)
                    ->where('id', $record->id)
                    ->update(['submissions' => json_encode($updatedSubmissions, JSON_THROW_ON_ERROR)]);
            });
    }

    /**
     * @return array<int, mixed>|null
     */
    private function decodeJsonList(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        $decodedValue = json_decode($value, true, flags: JSON_THROW_ON_ERROR);

        return is_array($decodedValue) ? $decodedValue : null;
    }
};
