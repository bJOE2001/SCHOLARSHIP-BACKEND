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
        $this->removeJsonListValue('scholarship_programs', 'requirements', 'Grades Transcript');
        $this->removeJsonListValue('scholarship_applications', 'missing_requirements', 'Grades Transcript');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }

    private function removeJsonListValue(string $table, string $column, string $valueToRemove): void
    {
        DB::table($table)
            ->select(['id', $column])
            ->orderBy('id')
            ->get()
            ->each(function (object $record) use ($table, $column, $valueToRemove): void {
                $values = $this->decodeJsonList($record->{$column});

                if ($values === null) {
                    return;
                }

                $updatedValues = array_values(array_filter(
                    $values,
                    static fn (mixed $value): bool => $value !== $valueToRemove,
                ));

                if ($updatedValues === $values) {
                    return;
                }

                DB::table($table)
                    ->where('id', $record->id)
                    ->update([$column => json_encode($updatedValues, JSON_THROW_ON_ERROR)]);
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
