<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Notifications\ListNotifications;
use App\Actions\Notifications\MarkAllNotificationsRead;
use App\Actions\Notifications\MarkNotificationRead;
use App\Actions\Notifications\UpdateNotificationPreferences;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Notifications\ListNotificationsRequest;
use App\Http\Requests\Api\V1\Notifications\UpdateNotificationPreferencesRequest;
use App\Http\Resources\UserNotificationResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    /**
     * The account's notifications, with the unread count in `meta`.
     */
    public function index(ListNotificationsRequest $request, ListNotifications $notifications): AnonymousResourceCollection
    {
        $user = $this->user($request);

        return UserNotificationResource::collection($notifications->handle(
            $user,
            $request->boolean('unread'),
            $request->integer('page', 1),
            $request->integer('per_page', 20),
        ))->additional(['meta' => ['unread_count' => $notifications->unreadCount($user)]]);
    }

    public function read(Request $request, string $notification, MarkNotificationRead $markRead): UserNotificationResource
    {
        return new UserNotificationResource($markRead->handle($this->user($request), $notification));
    }

    public function readAll(Request $request, MarkAllNotificationsRead $markAllRead): JsonResponse
    {
        return response()->json(['data' => ['marked' => $markAllRead->handle($this->user($request))]]);
    }

    public function updatePreferences(UpdateNotificationPreferencesRequest $request, UpdateNotificationPreferences $update): UserResource
    {
        /** @var array{email_notifications?: bool, locale?: string} $preferences */
        $preferences = $request->validated();

        if (array_key_exists('email_notifications', $preferences)) {
            $preferences['email_notifications'] = $request->boolean('email_notifications');
        }

        return new UserResource($update->handle($this->user($request), $preferences));
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
