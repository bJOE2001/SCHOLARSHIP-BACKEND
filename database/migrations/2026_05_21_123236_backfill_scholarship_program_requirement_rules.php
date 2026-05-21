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
        DB::table('scholarship_programs')
            ->select(['id', 'provider', 'requirements'])
            ->orderBy('id')
            ->get()
            ->each(function (object $program): void {
                $requirements = $this->decodeJsonList($program->requirements);

                if ($requirements === null) {
                    return;
                }

                $rules = array_values(array_filter(array_map(
                    fn (mixed $requirement): ?array => $this->requirementRule((string) $program->provider, $requirement),
                    $requirements,
                )));

                DB::table('scholarship_programs')
                    ->where('id', $program->id)
                    ->update(['requirement_rules' => json_encode($rules, JSON_THROW_ON_ERROR)]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('scholarship_programs')->update(['requirement_rules' => null]);
    }

    /**
     * @return array{name: string, stage: string, isRequired: bool, allowToFollow: bool}|null
     */
    private function requirementRule(string $provider, mixed $requirement): ?array
    {
        if (! is_string($requirement) || trim($requirement) === '') {
            return null;
        }

        $name = trim($requirement);

        return [
            'name' => $name,
            'stage' => 'application',
            'isRequired' => true,
            'allowToFollow' => strtoupper($provider) === 'SKEA' && $name !== 'Certificate of Indigency',
        ];
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
