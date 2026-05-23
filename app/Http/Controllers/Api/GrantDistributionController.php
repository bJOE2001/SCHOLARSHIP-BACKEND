<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\NotifyGrantBeneficiariesRequest;
use App\Http\Requests\ReleaseGrantRequest;
use App\Http\Requests\StoreGrantBatchRequest;
use App\Http\Requests\UpdateGrantBatchRequest;
use App\Http\Resources\GrantAnnouncementResource;
use App\Http\Resources\GrantBatchResource;
use App\Http\Resources\GrantBeneficiaryResource;
use App\Models\GrantAnnouncement;
use App\Models\GrantBatch;
use App\Models\GrantBeneficiary;
use App\Models\Scholar;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GrantDistributionController extends Controller
{
    /**
     * List grant distribution batches visible to the current user.
     */
    public function index(Request $request): JsonResponse
    {
        $currentUser = $request->user();

        abort_unless($currentUser instanceof User, 401);

        $batches = $this->visibleBatchQuery($currentUser)
            ->with($this->batchRelations())
            ->latest()
            ->get();

        $announcements = GrantAnnouncement::query()
            ->with($this->announcementRelations())
            ->whereIn('grant_batch_id', $batches->pluck('id'))
            ->latest('updated_at')
            ->get();

        return response()->json([
            'batches' => GrantBatchResource::collection($batches),
            'announcements' => GrantAnnouncementResource::collection($announcements),
        ]);
    }

    /**
     * List published grant announcements for public/student-facing pages.
     */
    public function publicAnnouncements(): JsonResponse
    {
        $announcements = GrantAnnouncement::query()
            ->with($this->announcementRelations())
            ->whereHas('batch', fn (Builder $query) => $query->whereIn('status', ['Open', 'Closed']))
            ->latest('updated_at')
            ->get();

        return response()->json([
            'announcements' => GrantAnnouncementResource::collection($announcements),
        ]);
    }

    /**
     * Create a grant batch and its beneficiaries.
     */
    public function store(StoreGrantBatchRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $program = ScholarshipProgram::findOrFail($validated['programId']);

        $this->assertCanManageProgram($request->user(), $program->id);
        $this->assertScholarsMatchProgram($validated['scholars'], $program->id);

        $batch = DB::transaction(function () use ($request, $validated, $program): GrantBatch {
            $batch = GrantBatch::create($this->batchAttributes($validated, $program, $request->user()));
            $this->syncBeneficiaries($batch, $validated['scholars']);

            return $batch;
        });

        return response()->json([
            'batch' => new GrantBatchResource($this->loadBatch($batch)),
        ], 201);
    }

    /**
     * Update a grant batch and refresh its beneficiary schedule.
     */
    public function update(UpdateGrantBatchRequest $request, GrantBatch $grantBatch): JsonResponse
    {
        $validated = $request->validated();
        $program = ScholarshipProgram::findOrFail($validated['programId']);

        $this->assertCanManageBatch($request->user(), $grantBatch);
        $this->assertCanManageProgram($request->user(), $program->id);
        $this->assertScholarsMatchProgram($validated['scholars'], $program->id);

        DB::transaction(function () use ($grantBatch, $validated, $program, $request): void {
            $grantBatch->update($this->batchAttributes($validated, $program, $request->user(), false));
            $this->syncBeneficiaries($grantBatch->refresh(), $validated['scholars']);
        });

        return response()->json([
            'batch' => new GrantBatchResource($this->loadBatch($grantBatch)),
        ]);
    }

    /**
     * Mark all or selected beneficiaries as notified.
     */
    public function notify(NotifyGrantBeneficiariesRequest $request, GrantBatch $grantBatch): JsonResponse
    {
        $this->assertCanManageBatch($request->user(), $grantBatch);

        $validated = $request->validated();
        $beneficiaryIds = collect($validated['beneficiaryIds'] ?? [])
            ->map(static fn (mixed $beneficiaryId): int => (int) $beneficiaryId)
            ->values();
        $beneficiaryQuery = $grantBatch->beneficiaries()
            ->when($beneficiaryIds->isNotEmpty(), fn (Builder $query) => $query->whereIn('id', $beneficiaryIds));
        $beneficiaries = $beneficiaryQuery->get();

        if ($beneficiaryIds->isNotEmpty() && $beneficiaries->count() !== $beneficiaryIds->count()) {
            throw ValidationException::withMessages([
                'beneficiaryIds' => ['Selected beneficiaries must belong to the grant batch.'],
            ]);
        }

        $notifiedAt = now();
        $beneficiaries->each(fn (GrantBeneficiary $beneficiary) => $beneficiary->update([
            'notified_at' => $notifiedAt,
        ]));

        $notifiedBeneficiaries = GrantBeneficiary::query()
            ->with(['batch.program'])
            ->whereIn('id', $beneficiaries->pluck('id'))
            ->orderBy('id')
            ->get();

        return response()->json([
            'batch' => new GrantBatchResource($this->loadBatch($grantBatch)),
            'beneficiaries' => GrantBeneficiaryResource::collection($notifiedBeneficiaries),
        ]);
    }

    /**
     * Create or refresh the public announcement for a grant batch.
     */
    public function announce(Request $request, GrantBatch $grantBatch): JsonResponse
    {
        $this->assertCanManageBatch($request->user(), $grantBatch);

        $batch = $this->loadBatch($grantBatch);
        $announcement = GrantAnnouncement::updateOrCreate(
            ['grant_batch_id' => $batch->id],
            [
                'created_by_id' => $request->user()?->id,
                'title' => "{$batch->title} beneficiaries list",
                'message' => "{$batch->program?->name} grant beneficiaries were notified. Open the attached beneficiary list for the claiming schedule.",
                'program_name' => $batch->program?->name ?? 'Scholarship Program',
                'semester' => $batch->semester,
                'school_year' => $batch->school_year,
                'venue' => $batch->venue,
                'total_beneficiaries' => $batch->beneficiaries->count(),
                'created_by_name' => $request->user()?->name ?? 'Scholarship Officer',
            ],
        );

        return response()->json([
            'announcement' => new GrantAnnouncementResource($announcement->load($this->announcementRelations())),
        ], $announcement->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Close a batch and mark remaining unclaimed grants.
     */
    public function close(Request $request, GrantBatch $grantBatch): JsonResponse
    {
        $this->assertCanManageBatch($request->user(), $grantBatch);

        DB::transaction(function () use ($grantBatch): void {
            $grantBatch->update(['status' => 'Closed']);
            $grantBatch->beneficiaries()
                ->where('claim_status', 'For Claiming')
                ->update(['claim_status' => 'Not Claimed']);
        });

        return response()->json([
            'batch' => new GrantBatchResource($this->loadBatch($grantBatch)),
        ]);
    }

    /**
     * Mark one beneficiary grant as released.
     */
    public function release(ReleaseGrantRequest $request, GrantBatch $grantBatch, GrantBeneficiary $grantBeneficiary): JsonResponse
    {
        $this->assertCanManageBatch($request->user(), $grantBatch);
        abort_unless($grantBeneficiary->grant_batch_id === $grantBatch->id, 404);

        if ($grantBeneficiary->claim_status === 'Claimed') {
            abort(422, 'This scholar already claimed this grant.');
        }

        if ($grantBeneficiary->claim_status !== 'For Claiming') {
            abort(422, 'Only beneficiaries marked For Claiming can be released.');
        }

        $validated = $request->validated();

        $grantBeneficiary->update([
            'claim_status' => 'Claimed',
            'claimed_at' => now(),
            'released_by_id' => $request->user()?->id,
            'released_by_name' => $request->user()?->name ?? 'Scholarship Officer',
            'reference_number' => $validated['referenceNumber'],
            'claim_method' => $validated['claimMethod'],
            'release_remarks' => $validated['remarks'] ?? null,
        ]);

        return response()->json([
            'batch' => new GrantBatchResource($this->loadBatch($grantBatch)),
            'beneficiary' => new GrantBeneficiaryResource($grantBeneficiary->refresh()->load(['batch.program'])),
        ]);
    }

    /**
     * Return grant batches visible to one user.
     */
    private function visibleBatchQuery(User $currentUser): Builder
    {
        return GrantBatch::query()
            ->when($currentUser->isStudent(), function (Builder $query) use ($currentUser): void {
                $query->whereHas('beneficiaries', fn (Builder $beneficiaryQuery) => $beneficiaryQuery->where('user_id', $currentUser->id));
            })
            ->when($currentUser->isOfficer() && ! $currentUser->isSuperAdmin(), function (Builder $query) use ($currentUser): void {
                $programIds = $this->assignedProgramIds($currentUser);

                $programIds === []
                    ? $query->whereRaw('1 = 0')
                    : $query->whereIn('scholarship_program_id', $programIds);
            })
            ->when(! $currentUser->isStudent() && ! $currentUser->isOfficer(), fn (Builder $query) => $query->whereRaw('1 = 0'));
    }

    /**
     * @return array<int, mixed>
     */
    private function batchRelations(): array
    {
        return [
            'program',
            'createdBy',
            'beneficiaries' => fn ($query) => $query
                ->with(['batch.program'])
                ->orderBy('assigned_claim_date')
                ->orderBy('id'),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function announcementRelations(): array
    {
        return [
            'createdBy',
            'batch' => fn ($query) => $query->with($this->batchRelations()),
        ];
    }

    /**
     * Load a grant batch with the relations needed by the frontend.
     */
    private function loadBatch(GrantBatch $grantBatch): GrantBatch
    {
        return $grantBatch->refresh()->load($this->batchRelations());
    }

    /**
     * Convert request payloads to grant batch columns.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function batchAttributes(array $validated, ScholarshipProgram $program, ?User $currentUser, bool $isCreation = true): array
    {
        $title = trim((string) ($validated['title'] ?? ''));

        $attributes = [
            'scholarship_program_id' => $program->id,
            'created_by_id' => $isCreation ? $currentUser?->id : null,
            'title' => $title !== '' ? $title : "{$program->name} {$validated['semester']} Grant Release",
            'semester' => $validated['semester'],
            'school_year' => $validated['schoolYear'],
            'amount' => $validated['amount'],
            'claiming_start_date' => $validated['claimingStartDate'],
            'claiming_end_date' => $validated['claimingEndDate'],
            'venue' => $validated['venue'],
            'daily_limit' => $validated['dailyLimit'],
            'remarks' => $validated['remarks'] ?? null,
            'status' => $validated['status'] ?? 'Draft',
        ];

        if (! $isCreation) {
            unset($attributes['created_by_id']);
        }

        return $attributes;
    }

    /**
     * Create or update beneficiaries for one batch.
     *
     * @param  array<int, array<string, mixed>>  $scholarRows
     */
    private function syncBeneficiaries(GrantBatch $grantBatch, array $scholarRows): void
    {
        $normalizedScholarRows = $this->normalizeScholarRows($scholarRows);
        $scholarIds = $normalizedScholarRows->pluck('id');
        $heldScholarIds = $normalizedScholarRows
            ->filter(fn (array $scholarRow): bool => $scholarRow['onHold'])
            ->pluck('id')
            ->map(static fn (int $scholarId): string => (string) $scholarId)
            ->all();
        $scholars = Scholar::query()
            ->with('user')
            ->whereIn('id', $scholarIds)
            ->get()
            ->keyBy('id');
        $existingBeneficiaries = $grantBatch->beneficiaries()->get()->keyBy('scholar_id');
        $keptBeneficiaryIds = [];

        $normalizedScholarRows->each(function (array $scholarRow, int $index) use ($grantBatch, $scholars, $existingBeneficiaries, $heldScholarIds, &$keptBeneficiaryIds): void {
            $scholar = $scholars->get($scholarRow['id']);

            if (! $scholar instanceof Scholar) {
                return;
            }

            $existingBeneficiary = $existingBeneficiaries->get($scholar->id);
            $schedule = $this->claimScheduleFor($grantBatch, $index);
            $isClaimed = $existingBeneficiary?->claim_status === 'Claimed';
            $claimStatus = $isClaimed
                ? 'Claimed'
                : $this->claimStatusForBatch($grantBatch->status, in_array((string) $scholar->id, $heldScholarIds, true));

            $beneficiary = $existingBeneficiary ?? new GrantBeneficiary([
                'grant_batch_id' => $grantBatch->id,
                'scholar_id' => $scholar->id,
                'reference_number' => $this->buildReferenceNumber($grantBatch, $index),
            ]);

            $beneficiary->fill([
                'grant_batch_id' => $grantBatch->id,
                'scholar_id' => $scholar->id,
                'user_id' => $scholar->user_id,
                'scholar_identifier' => $scholar->scholar_id ?: 'SCH-'.str_pad((string) $scholar->id, 5, '0', STR_PAD_LEFT),
                'scholar_name' => $scholar->name,
                'barangay' => $scholar->user?->barangay ?: $scholar->address ?: 'Not recorded',
                'course' => $scholar->course ?: 'Not recorded',
                'amount' => $grantBatch->amount,
                'assigned_claim_date' => $grantBatch->status === 'Open' ? $schedule['date'] : null,
                'time_slot' => $grantBatch->status === 'Open' ? $schedule['timeSlot'] : null,
                'claim_status' => $claimStatus,
            ]);

            $beneficiary->save();
            $keptBeneficiaryIds[] = $beneficiary->id;
        });

        $grantBatch->beneficiaries()
            ->whereNotIn('id', $keptBeneficiaryIds)
            ->delete();
    }

    /**
     * @param  array<int, array<string, mixed>>  $scholarRows
     */
    private function normalizeScholarRows(array $scholarRows): Collection
    {
        return collect($scholarRows)
            ->map(fn (array $scholarRow): array => [
                'id' => (int) $scholarRow['id'],
                'onHold' => (bool) ($scholarRow['onHold'] ?? false),
            ])
            ->unique('id')
            ->values();
    }

    /**
     * Return a calculated claiming schedule for a beneficiary index.
     *
     * @return array{date: string, timeSlot: string}
     */
    private function claimScheduleFor(GrantBatch $grantBatch, int $index): array
    {
        $dailyLimit = max($grantBatch->daily_limit, 1);
        $dailyIndex = $index % $dailyLimit;
        $claimDate = Carbon::parse($grantBatch->claiming_start_date)->addDays(intdiv($index, $dailyLimit));

        return [
            'date' => $claimDate->toDateString(),
            'timeSlot' => $dailyIndex < (int) ceil($dailyLimit / 2) ? '8:00 AM - 12:00 PM' : '1:00 PM - 5:00 PM',
        ];
    }

    /**
     * Determine the initial claim status for a beneficiary.
     */
    private function claimStatusForBatch(string $batchStatus, bool $isHeld): string
    {
        if ($isHeld || $batchStatus !== 'Open') {
            return 'On Hold';
        }

        return 'For Claiming';
    }

    /**
     * Build a unique beneficiary reference number.
     */
    private function buildReferenceNumber(GrantBatch $grantBatch, int $index): string
    {
        $prefix = Str::upper(Str::substr(preg_replace('/[^A-Za-z0-9]/', '', $grantBatch->program?->name ?? 'GRANT') ?: 'GRNT', 0, 4));

        do {
            $referenceNumber = sprintf(
                '%s-%s-%04d-%s',
                $prefix ?: 'GRNT',
                now()->format('ymdHis'),
                $index + 1,
                Str::upper(Str::random(4)),
            );
        } while (GrantBeneficiary::query()->where('reference_number', $referenceNumber)->exists());

        return $referenceNumber;
    }

    /**
     * Ensure all selected scholars belong to the selected program.
     *
     * @param  array<int, array<string, mixed>>  $scholarRows
     */
    private function assertScholarsMatchProgram(array $scholarRows, int $programId): void
    {
        $scholarIds = collect($scholarRows)
            ->pluck('id')
            ->map(static fn (mixed $scholarId): int => (int) $scholarId)
            ->all();
        $mismatchedScholarIds = Scholar::query()
            ->whereIn('id', $scholarIds)
            ->where(function (Builder $query) use ($programId): void {
                $query
                    ->where('scholarship_program_id', '!=', $programId)
                    ->orWhereNull('scholarship_program_id');
            })
            ->pluck('id')
            ->all();

        if ($mismatchedScholarIds !== []) {
            throw ValidationException::withMessages([
                'scholars' => ['Selected scholars must belong to the selected scholarship program.'],
            ]);
        }
    }

    /**
     * Ensure the user can manage a batch's program.
     */
    private function assertCanManageBatch(?User $currentUser, GrantBatch $grantBatch): void
    {
        $this->assertCanManageProgram($currentUser, (int) $grantBatch->scholarship_program_id);
    }

    /**
     * Ensure the user can manage one program.
     */
    private function assertCanManageProgram(?User $currentUser, int $programId): void
    {
        abort_unless($currentUser?->isOfficer(), 403);

        if ($currentUser->isSuperAdmin()) {
            return;
        }

        abort_unless(in_array($programId, $this->assignedProgramIds($currentUser), true), 403);
    }

    /**
     * @return array<int, int>
     */
    private function assignedProgramIds(User $currentUser): array
    {
        return $currentUser->assignedPrograms()
            ->pluck('scholarship_programs.id')
            ->map(static fn (mixed $programId): int => (int) $programId)
            ->all();
    }
}
