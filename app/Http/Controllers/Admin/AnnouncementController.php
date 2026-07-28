<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\NotiType;
use App\Enums\OrgType;
use App\Enums\Severity;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Organization;
use App\Services\PushNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 관리자 공지사항 발송 — 본사(HQ) 전용.
 * 공지를 저장하면 NotificationPushObserver 가 자동으로 FCM 푸시를 발송한다.
 */
class AnnouncementController extends Controller
{
    public function create(): View
    {
        return view('admin.announcements', [
            'organizations' => Organization::query()->where('is_active', true)
                ->orderBy('org_type')->orderBy('name')->get(['id', 'name', 'org_type']),
            'recent' => Notification::query()
                ->where('noti_type', NotiType::NOTICE)
                ->with('targetOrg:id,name')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(),
        ]);
    }

    public function store(Request $request, PushNotifier $push): RedirectResponse
    {
        $roleValues = array_map(fn (OrgType $t) => $t->value, OrgType::cases());
        $sevValues = array_map(fn (Severity $s) => $s->value, Severity::cases());

        $data = $request->validate([
            'title' => 'required|string|max:120',
            'message' => 'nullable|string|max:2000',
            'link_url' => 'nullable|string|max:255',
            'severity' => 'required|in:'.implode(',', $sevValues),
            'target_mode' => 'required|in:all,role,org',
            'roles' => 'required_if:target_mode,role|array',
            'roles.*' => 'in:'.implode(',', $roleValues),
            'org_ids' => 'required_if:target_mode,org|array',
            'org_ids.*' => 'integer|exists:organizations,id',
        ], [
            'title.required' => '제목을 입력해 주세요.',
            'target_mode.required' => '발송 대상을 선택해 주세요.',
            'roles.required_if' => '대상 역할을 하나 이상 선택해 주세요.',
            'org_ids.required_if' => '대상 조직을 하나 이상 선택해 주세요.',
        ]);

        $base = [
            'noti_type' => NotiType::NOTICE,
            'severity' => Severity::from($data['severity']),
            'title' => $data['title'],
            'message' => $data['message'] ?? null,
            'link_url' => $data['link_url'] ?? '/notifications',
            'is_read' => false,
        ];

        /** @var list<Notification> $created */
        $created = [];

        if ($data['target_mode'] === 'all') {
            $created[] = Notification::create($base + ['target_role' => null, 'target_org_id' => null]);
        } elseif ($data['target_mode'] === 'role') {
            foreach (array_unique($data['roles']) as $role) {
                $created[] = Notification::create($base + ['target_role' => $role, 'target_org_id' => null]);
            }
        } else { // org
            foreach (array_unique($data['org_ids']) as $orgId) {
                $created[] = Notification::create($base + ['target_role' => null, 'target_org_id' => (int) $orgId]);
            }
        }

        // 발송 대상(수신자) 수 — 여러 알림의 대상을 합집합으로 계산(중복 제거).
        $recipients = [];
        foreach ($created as $noti) {
            $recipients = array_merge($recipients, $push->recipientIds($noti));
        }
        $count = count(array_unique($recipients));

        return redirect()
            ->route('admin.announcements')
            ->with('announced', "공지를 발송했습니다. 대상 사용자 {$count}명 (푸시 발송은 앱 알림 등록 기기에 한함).");
    }
}
