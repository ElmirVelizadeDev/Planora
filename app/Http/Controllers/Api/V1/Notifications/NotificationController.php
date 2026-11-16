<?php

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Notifications\IndexNotificationsRequest;
use App\Http\Resources\Api\V1\Notifications\NotificationResource;
use App\Services\Api\V1\Notifications\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(IndexNotificationsRequest $request, NotificationService $notifications): JsonResponse
    {
        $recipient = $request->user();
        $paginator = $notifications->paginate($recipient, $request->validated());

        return response()->json([
            'data' => NotificationResource::collection($paginator->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'unread_count' => $notifications->unreadCount($recipient),
            ],
        ]);
    }

    public function markRead(Request $request, int $notification, NotificationService $notifications): JsonResponse
    {
        $record = $notifications->markRead($request->user(), $notification);

        return response()->json([
            'data' => NotificationResource::make($record)->resolve($request),
            'meta' => ['unread_count' => $notifications->unreadCount($request->user())],
        ]);
    }

    public function markAllRead(Request $request, NotificationService $notifications): JsonResponse
    {
        $updated = $notifications->markAllRead($request->user());

        return response()->json(['data' => ['updated' => $updated, 'unread_count' => 0]]);
    }
}
