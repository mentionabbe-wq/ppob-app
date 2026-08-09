<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\NotificationResource;
use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends BaseApiController
{
    /** @OA\Get(path="/notifications", tags={"Notifikasi"}, security={{"bearerAuth":{}}}, summary="Daftar notifikasi") */
    public function index(Request $request): JsonResponse
    {
        $notifications = AppNotification::visibleTo($request->user()->id)
            ->when($request->input('type'), fn ($q, $v) => $q->where('type', $v))
            ->when($request->boolean('unread_only'), fn ($q) => $q->unread())
            ->latest('id')
            ->paginate((int) $request->integer('per_page', 20));

        return $this->ok(NotificationResource::collection($notifications));
    }

    /** @OA\Get(path="/notifications/unread-count", tags={"Notifikasi"}, security={{"bearerAuth":{}}}, summary="Jumlah belum dibaca") */
    public function unreadCount(Request $request): JsonResponse
    {
        return $this->ok([
            'count' => AppNotification::visibleTo($request->user()->id)->unread()->count(),
        ]);
    }

    /** @OA\Put(path="/notifications/{id}/read", tags={"Notifikasi"}, security={{"bearerAuth":{}}}, summary="Tandai dibaca") */
    public function markAsRead(Request $request, AppNotification $notification): JsonResponse
    {
        abort_unless(
            $notification->user_id === null || $notification->user_id === $request->user()->id,
            403,
        );

        $notification->update(['read_at' => now()]);

        return $this->ok(null, 'Notifikasi ditandai sudah dibaca.');
    }

    /** @OA\Put(path="/notifications/read-all", tags={"Notifikasi"}, security={{"bearerAuth":{}}}, summary="Tandai semua dibaca") */
    public function markAllAsRead(Request $request): JsonResponse
    {
        AppNotification::where('user_id', $request->user()->id)->unread()->update(['read_at' => now()]);

        return $this->ok(null, 'Semua notifikasi ditandai sudah dibaca.');
    }
}
