<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNotificationRequest;
use App\Http\Resources\ScholarshipNotificationResource;
use App\Models\ScholarshipNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * List visible notifications for the current user.
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = $this->visibleNotificationsQuery($request)
            ->latest('notified_at')
            ->latest()
            ->get();

        return response()->json([
            'notifications' => ScholarshipNotificationResource::collection($notifications),
        ]);
    }

    /**
     * Send a notification to one user or a whole role.
     */
    public function store(StoreNotificationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $notification = ScholarshipNotification::create([
            'user_id' => $validated['userId'] ?? null,
            'role' => $validated['role'] ?? null,
            'type' => $validated['type'],
            'title' => $validated['title'],
            'message' => $validated['message'],
            'notified_at' => now(),
            'payload' => array_merge($validated['payload'] ?? [], [
                'sentByUserId' => $request->user()?->id,
            ]),
        ]);

        return response()->json([
            'notification' => new ScholarshipNotificationResource($notification),
        ], 201);
    }

    /**
     * Mark one notification as read.
     */
    public function markRead(Request $request, ScholarshipNotification $notification): JsonResponse
    {
        $this->assertVisible($request, $notification);

        $notification->update([
            'read_at' => now(),
        ]);

        return response()->json([
            'notification' => new ScholarshipNotificationResource($notification),
        ]);
    }

    /**
     * Mark all visible notifications as read.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $this->visibleNotificationsQuery($request)->update([
            'read_at' => now(),
        ]);

        return response()->noContent();
    }

    /**
     * Get the notifications visible to the current user.
     */
    private function visibleNotificationsQuery(Request $request)
    {
        $currentUser = $request->user();

        return ScholarshipNotification::query()
            ->when($currentUser !== null, function ($query) use ($currentUser): void {
                $query->where(function ($nestedQuery) use ($currentUser): void {
                    $nestedQuery
                        ->where('user_id', $currentUser->id)
                        ->orWhere(function ($roleQuery) use ($currentUser): void {
                            $roleQuery
                                ->whereNull('user_id')
                                ->where(function ($roleMatchQuery) use ($currentUser): void {
                                    $roleMatchQuery
                                        ->where('role', $currentUser->role)
                                        ->orWhereNull('role');
                                });
                        });
                });
            });
    }

    /**
     * Ensure the notification can be viewed by the current user.
     */
    private function assertVisible(Request $request, ScholarshipNotification $notification): void
    {
        $currentUser = $request->user();

        abort_unless(
            $currentUser !== null
                && (
                    $notification->user_id === $currentUser->id
                    || (
                        $notification->user_id === null
                        && (
                            $notification->role === $currentUser->role
                            || $notification->role === null
                        )
                    )
                ),
            403,
        );
    }
}
