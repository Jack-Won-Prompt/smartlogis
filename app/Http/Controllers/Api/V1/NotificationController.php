<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 알림 센터. 가시성은 Notification::scopeVisibleTo() 가 담당한다(자기 조직 + 자기 역할).
 */
class NotificationController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Notification::query()
            ->visibleTo($user)
            ->when($request->boolean('unread_only'), fn ($q) => $q->unread())
            ->when($request->string('noti_type')->toString(), fn ($q, $v) => $q->where('noti_type', $v))
            ->orderByDesc('id');

        $paginator = $query->paginate($this->pageSize($request), ['*'], 'page', max($request->integer('page', 1), 1));

        $response = $this->paged($paginator, fn (Notification $n) => [
            'id' => $n->id,
            'noti_type' => $n->noti_type->value,
            'noti_label' => $n->noti_type->label(),
            'severity' => $n->severity->value,
            'severity_label' => $n->severity->label(),
            'title' => $n->title,
            'message' => $n->message,
            'link_url' => $n->link_url,
            'is_read' => $n->is_read,
            'created_at' => $n->created_at?->toIso8601String(),
        ]);

        $payload = $response->getData(true);
        $payload['unread_count'] = Notification::query()->visibleTo($user)->unread()->count();

        return response()->json($payload);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'unread_count' => Notification::query()->visibleTo($request->user())->unread()->count(),
        ]);
    }

    public function read(Request $request, int $id): JsonResponse
    {
        $notification = Notification::query()->visibleTo($request->user())->find($id);

        if ($notification === null) {
            return response()->json(['message' => '알림을 찾을 수 없습니다.'], 404);
        }

        $notification->update(['is_read' => true, 'read_at' => now()]);

        return $this->ok('읽음 처리되었습니다.');
    }

    public function readAll(Request $request): JsonResponse
    {
        $count = Notification::query()
            ->visibleTo($request->user())
            ->unread()
            ->update(['is_read' => true, 'read_at' => now()]);

        return $this->ok("{$count}건을 읽음 처리했습니다.", ['count' => $count]);
    }
}
