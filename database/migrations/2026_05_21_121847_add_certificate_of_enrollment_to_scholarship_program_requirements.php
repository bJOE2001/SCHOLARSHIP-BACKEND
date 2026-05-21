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
        $this->addRequirement('Certificate of Enrollment / COE');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->removeRequirement('Certificate of Enrollment / COE');
    }

    private function addRequirement(string $requirement): void
    {
        DB::table('scholarship_programs')
            ->select(['id', 'requirements'])
            ->orderBy('id')
            ->get()
            ->each(function (object $program) use ($requirement): void {
                $requirements = $this->decodeJsonList($program->requirements);

                if ($requirements === null || in_array($requirement, $requirements, true)) {
                    return;
                }

                array_unshift($requirements, $requirement);

                DB::table('scholarship_programs')
                    ->where('id', $program->id)
                    ->update(['requirements' => json_encode($requirements, JSON_THROW_ON_ERROR)]);
            });
    }

    private function removeRequirement(string $requirement): void
    {
        DB::table('scholarship_programs')
            ->select(['id', 'requirements'])
            ->orderBy('id')
            ->get()
            ->each(function (object $program) use ($requirement): void {
                $requirements = $this->decodeJsonList($program->requirements);

                if ($requirements === null) {
                    return;
                }

                $updatedRequirements = array_values(array_filter(
                    $requirements,
                    static fn (mixed $value): bool => $value !== $requirement,
                ));

                if ($updatedRequirements === $requirements) {
                    return;
                }

                DB::table('scholarship_programs')
                    ->where('id', $program->id)
                    ->update(['requirements' => json_encode($updatedRequirements, JSON_THROW_ON_ERROR)]);
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
