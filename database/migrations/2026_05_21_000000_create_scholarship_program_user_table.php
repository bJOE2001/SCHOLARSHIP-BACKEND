<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('scholarship_program_user', function (Blueprint $table) {
            $table->foreignId('scholarship_program_id')->constrained('scholarship_programs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['scholarship_program_id', 'user_id']);
        });

        if (Schema::hasColumn('users', 'assigned_program_ids')) {
            DB::table('users')
                ->whereNotNull('assigned_program_ids')
                ->select(['id', 'assigned_program_ids'])
                ->orderBy('id')
                ->each(function (object $user): void {
                    foreach ($this->decodeIds($user->assigned_program_ids) as $programId) {
                        $this->insertAssignment((int) $programId, (int) $user->id);
                    }
                });
        }

        if (Schema::hasColumn('scholarship_programs', 'assigned_admin_ids')) {
            DB::table('scholarship_programs')
                ->whereNotNull('assigned_admin_ids')
                ->select(['id', 'assigned_admin_ids'])
                ->orderBy('id')
                ->each(function (object $program): void {
                    foreach ($this->decodeIds($program->assigned_admin_ids) as $userId) {
                        $this->insertAssignment((int) $program->id, (int) $userId);
                    }
                });
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'assigned_program_ids')) {
                $table->dropColumn('assigned_program_ids');
            }
        });

        Schema::table('scholarship_programs', function (Blueprint $table) {
            if (Schema::hasColumn('scholarship_programs', 'assigned_admin_ids')) {
                $table->dropColumn('assigned_admin_ids');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('assigned_program_ids')->nullable();
        });

        Schema::table('scholarship_programs', function (Blueprint $table) {
            $table->json('assigned_admin_ids')->nullable();
        });

        DB::table('users')
            ->select('id')
            ->orderBy('id')
            ->each(function (object $user): void {
                $programIds = DB::table('scholarship_program_user')
                    ->where('user_id', $user->id)
                    ->pluck('scholarship_program_id')
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->all();

                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['assigned_program_ids' => json_encode($programIds)]);
            });

        DB::table('scholarship_programs')
            ->select('id')
            ->orderBy('id')
            ->each(function (object $program): void {
                $userIds = DB::table('scholarship_program_user')
                    ->where('scholarship_program_id', $program->id)
                    ->pluck('user_id')
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->all();

                DB::table('scholarship_programs')
                    ->where('id', $program->id)
                    ->update(['assigned_admin_ids' => json_encode($userIds)]);
            });

        Schema::dropIfExists('scholarship_program_user');
    }

    /**
     * Decode a legacy JSON id list.
     *
     * @return array<int, int>
     */
    private function decodeIds(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $decoded = is_array($value) ? $value : json_decode((string) $value, true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $decoded),
            static fn (int $id): bool => $id > 0,
        )));
    }

    private function insertAssignment(int $programId, int $userId): void
    {
        DB::table('scholarship_program_user')->updateOrInsert(
            [
                'scholarship_program_id' => $programId,
                'user_id' => $userId,
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
};
