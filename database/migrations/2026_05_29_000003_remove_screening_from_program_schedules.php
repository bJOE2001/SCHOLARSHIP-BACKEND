<?php

use App\Models\ScholarshipProgram;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Remove the deprecated review/screening date from existing program schedule JSON.
     */
    public function up(): void
    {
        ScholarshipProgram::query()
            ->whereNotNull('schedule')
            ->each(function (ScholarshipProgram $program): void {
                $schedule = $program->schedule;

                if (! is_array($schedule) || ! array_key_exists('screening', $schedule)) {
                    return;
                }

                unset($schedule['screening']);

                $program->forceFill(['schedule' => $schedule])->save();
            });
    }

    /**
     * The removed screening values cannot be restored safely.
     */
    public function down(): void
    {
        //
    }
};
