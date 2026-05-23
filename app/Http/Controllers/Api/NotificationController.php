<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNotificationRequest;
use App\Http\Resources\ScholarshipNotificationResource;
use App\Models\ScholarshipNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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
    public function markAllRead(Request $request): Response
    {
        $this->visibleNotificationsQuery($request)->update([
            'read_at' => now(),
        ]);

        return response()->noContent();
    }

    /**
     * Get the notifications visible to the current user.
     */
    private function visibleNotificationsQuery(Request $request): Builder
    {
        $currentUser = $request->user();

        abort_unless($currentUser instanceof User, 401);

        return ScholarshipNotification::query()
            ->where(function (Builder $targetQuery) use ($currentUser): void {
                $targetQuery
                    ->where('user_id', $currentUser->id)
                    ->orWhere(function (Builder $roleQuery) use ($currentUser): void {
                        $roleQuery
                            ->whereNull('user_id')
                            ->where(function (Builder $roleMatchQuery) use ($currentUser): void {
                                $roleMatchQuery
                                    ->where('role', $currentUser->role)
                                    ->orWhereNull('role');
                            })
                            ->where(function (Builder $dateQuery) use ($currentUser): void {
                                $dateQuery
                                    ->where('notified_at', '>=', $currentUser->created_at)
                                    ->orWhere(function (Builder $fallbackDateQuery) use ($currentUser): void {
                                        $fallbackDateQuery
                                            ->whereNull('notified_at')
                                            ->where('created_at', '>=', $currentUser->created_at);
                                    });
                            });
                    });
            });
    }

    /**
     * Determine whether a notification is visible to a specific user.
     */
    private function notificationVisibleToUser(ScholarshipNotification $notification, User $currentUser): bool
    {
        if ($notification->user_id === $currentUser->id) {
            return true;
        }

        if (
            $notification->user_id !== null
            || (
                $notification->role !== $currentUser->role
                && $notification->role !== null
            )
        ) {
            return false;
        }

        return $this->notificationDeliveredAfterUserCreation($notification, $currentUser);
    }

    /**
     * Determine whether a role-wide notification was delivered after the user joined.
     */
    private function notificationDeliveredAfterUserCreation(ScholarshipNotification $notification, User $currentUser): bool
    {
        if ($currentUser->created_at === null) {
            return true;
        }

        $notifiedAt = $notification->notified_at ?? $notification->created_at;

        return $notifiedAt !== null && $notifiedAt->greaterThanOrEqualTo($currentUser->created_at);
    }

    /**
     * Ensure the notification can be viewed by the current user.
     */
    private function assertVisible(Request $request, ScholarshipNotification $notification): void
    {
        $currentUser = $request->user();

        abort_unless(
            $currentUser instanceof User && $this->notificationVisibleToUser($notification, $currentUser),
            403,
        );
    }
}
