<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAnnouncementRequest;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use App\Models\ScholarshipNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    /**
     * List published announcements for public-facing pages.
     */
    public function publicIndex(): JsonResponse
    {
        $announcements = $this->publishedAnnouncementsQuery()
            ->latest('published_at')
            ->latest()
            ->get();

        return response()->json([
            'announcements' => AnnouncementResource::collection($announcements),
        ]);
    }

    /**
     * List announcements visible to the current user.
     */
    public function index(Request $request): JsonResponse
    {
        $announcements = $this->publishedAnnouncementsQuery()
            ->when($request->user()?->isOfficer() && ! $request->user()?->isSuperAdmin(), function (Builder $query) use ($request): void {
                $programIds = $this->assignedProgramIds($request->user());

                $query->where(function (Builder $programQuery) use ($programIds): void {
                    $programQuery
                        ->whereNull('scholarship_program_id')
                        ->orWhereIn('scholarship_program_id', $programIds);
                });
            })
            ->latest('published_at')
            ->latest()
            ->get();

        return response()->json([
            'announcements' => AnnouncementResource::collection($announcements),
        ]);
    }

    /**
     * Publish an announcement and notify students.
     */
    public function store(StoreAnnouncementRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $programId = isset($validated['programId']) ? (int) $validated['programId'] : null;

        abort_unless($this->canManageProgram($request->user(), $programId), 403);

        $announcement = Announcement::create([
            'scholarship_program_id' => $programId,
            'created_by_id' => $request->user()?->id,
            'title' => $validated['title'],
            'message' => $validated['message'],
            'pin' => (bool) ($validated['pin'] ?? false),
            'status' => $validated['status'] ?? 'Published',
            'published_at' => ($validated['status'] ?? 'Published') === 'Published' ? now() : null,
        ]);

        if ($announcement->status === 'Published') {
            $this->notifyStudents($announcement);
        }

        return response()->json([
            'announcement' => new AnnouncementResource($announcement->load(['program', 'createdBy'])),
        ], 201);
    }

    /**
     * Delete a published announcement.
     */
    public function destroy(Request $request, Announcement $announcement): JsonResponse
    {
        abort_unless($this->canManageProgram($request->user(), $announcement->scholarship_program_id), 403);

        ScholarshipNotification::query()
            ->where('payload->announcementId', $announcement->id)
            ->delete();

        $announcement->delete();

        return response()->json([
            'message' => 'Announcement deleted.',
        ]);
    }

    /**
     * Determine whether a user can create an announcement for a program.
     */
    private function canManageProgram(?User $user, ?int $programId): bool
    {
        if ($user === null || ! $user->isOfficer()) {
            return false;
        }

        if ($programId === null || $user->isSuperAdmin()) {
            return true;
        }

        return in_array($programId, $this->assignedProgramIds($user), true);
    }

    /**
     * Return assigned scholarship program ids for an officer.
     *
     * @return array<int, int>
     */
    private function assignedProgramIds(User $user): array
    {
        return array_values(array_map('intval', $user->assigned_program_ids ?? []));
    }

    /**
     * Start a published announcements query with shared eager loads.
     */
    private function publishedAnnouncementsQuery(): Builder
    {
        return Announcement::query()
            ->with(['program', 'createdBy'])
            ->where('status', 'Published');
    }

    /**
     * Send the visible student notification linked to a published announcement.
     */
    private function notifyStudents(Announcement $announcement): void
    {
        ScholarshipNotification::create([
            'user_id' => null,
            'role' => 'student',
            'type' => 'admin',
            'title' => $announcement->title,
            'message' => $announcement->message,
            'notified_at' => now(),
            'payload' => [
                'announcementId' => $announcement->id,
                'programId' => $announcement->scholarship_program_id,
                'pinned' => $announcement->pin,
            ],
        ]);
    }
}
