<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * 알림 센터. 자기 조직/역할 대상 알림을 조회하고 읽음 처리한다.
 * 15초 폴링으로 미읽음 수를 갱신한다(DESIGN.md §5.6).
 */
class NotificationCenter extends Component
{
    use WithPagination;

    #[Url]
    public string $filter = 'all'; // all | unread

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
        $this->resetPage();
    }

    public function markRead(int $id): void
    {
        $notification = $this->baseQuery()->whereKey($id)->first();
        $notification?->update(['is_read' => true, 'read_at' => now()]);
    }

    public function markAllRead(): void
    {
        $count = $this->baseQuery()->where('is_read', false)->update(['is_read' => true, 'read_at' => now()]);

        $this->dispatch('toast', message: "알림 {$count}건을 읽음 처리했습니다.", tone: 'ok');
    }

    /** 알림 삭제 — 본사(HQ) 전용. 알림은 공유 데이터라 관리자만 정리한다. */
    public function delete(int $id): void
    {
        if (! Auth::user()?->isHq()) {
            return;
        }
        Notification::query()->whereKey($id)->delete();
    }

    /** 읽은 알림 일괄 삭제 — 본사(HQ) 전용. */
    public function deleteRead(): void
    {
        if (! Auth::user()?->isHq()) {
            return;
        }
        $count = Notification::query()->where('is_read', true)->delete();
        $this->resetPage();
        $this->dispatch('toast', message: "읽은 알림 {$count}건을 삭제했습니다.", tone: 'ok');
    }

    /** @return Builder<Notification> */
    private function baseQuery()
    {
        /** @var User $user */
        $user = Auth::user();

        return Notification::query()->visibleTo($user);
    }

    public function unreadCount(): int
    {
        return $this->baseQuery()->where('is_read', false)->count();
    }

    public function render(): View
    {
        $query = $this->baseQuery();
        if ($this->filter === 'unread') {
            $query->where('is_read', false);
        }

        return view('livewire.notification-center', [
            'notifications' => $query->orderByDesc('created_at')->paginate(15),
            'unreadCount' => $this->unreadCount(),
        ]);
    }
}
