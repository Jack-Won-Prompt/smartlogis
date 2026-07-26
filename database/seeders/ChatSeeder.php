<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\OrgType;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * 채팅 데모 데이터 — 역할(영역)별 대표 사용자에게 각 10개 대화 + 메시지를 생성한다.
 * 멱등(1:1 은 findBetween, 그룹은 이름으로 중복 방지) · faker 미사용(운영 실행 가능).
 * 실행: php artisan db:seed --class=ChatSeeder --force
 */
class ChatSeeder extends Seeder
{
    private const PER_ANCHOR = 10;

    /** @var list<string> 데모 메시지 문구(결정적) */
    private const SAMPLES = [
        '안녕하세요, 확인 부탁드립니다.',
        '네, 확인했습니다. 바로 처리하겠습니다.',
        '해당 품목 재고 관련해서 문의드립니다.',
        '오늘 중으로 반영해서 다시 공유드릴게요.',
        '승인 완료했습니다. 감사합니다.',
        '배송 일정 확인해 주실 수 있을까요?',
        '내일 오전에 다시 연락드리겠습니다.',
        '감사합니다. 좋은 하루 되세요.',
    ];

    public function run(): void
    {
        /** @var Collection<int, User> $all */
        $all = User::query()->orderBy('id')->get();
        if ($all->count() < 2) {
            return;
        }

        // 역할(영역)별 대표(앵커) 1명씩
        $anchors = collect(OrgType::cases())
            ->map(fn (OrgType $role) => $all->firstWhere('role', $role))
            ->filter()
            ->values();

        if ($anchors->isEmpty()) {
            $anchors = $all->take(4)->values();
        }

        $anchorIds = $anchors->map(fn (User $u) => $u->id)->all();
        $partners = $all->reject(fn (User $u) => in_array($u->id, $anchorIds, true))->values();
        $partnerCount = max(1, $partners->count());

        // 영역별 10개 1:1 대화(파트너 슬라이스로 겹치지 않게)
        foreach ($anchors as $ai => $anchor) {
            for ($i = 0; $i < self::PER_ANCHOR; $i++) {
                /** @var User $partner */
                $partner = $partners[(($ai * self::PER_ANCHOR) + $i) % $partnerCount];
                if ($partner->id === $anchor->id) {
                    continue;
                }
                if (Conversation::findBetween($anchor->id, $partner->id) !== null) {
                    continue; // 멱등
                }

                $conv = Conversation::create(['is_group' => false]);
                $conv->participants()->attach([$anchor->id, $partner->id], ['last_read_at' => null]);
                $this->seedMessages($conv->id, [$anchor->id, $partner->id], $i);

                // 각 앵커의 첫 대화엔 파일 첨부 메시지도 포함
                if ($i === 0) {
                    $this->seedFileMessage($conv->id, $anchor->id);
                }
            }
        }

        // 영역 혼합 그룹 채팅
        $groups = [
            ['본사·창고·병원 공지방', 3],
            ['공급사 협의방', 4],
        ];
        foreach ($groups as $gi => [$name, $extra]) {
            if (Conversation::where('is_group', true)->where('name', $name)->exists()) {
                continue; // 멱등
            }
            $members = $all->take($extra + 1)->values();
            if ($members->count() < 2) {
                continue;
            }
            $conv = Conversation::create(['name' => $name, 'is_group' => true]);
            $conv->participants()->attach($members->map(fn (User $u) => $u->id)->all(), ['last_read_at' => null]);
            $this->seedMessages($conv->id, $members->map(fn (User $u) => $u->id)->all(), $gi);
        }
    }

    /** 샘플 파일(문서 + 이미지) 첨부 메시지. */
    private function seedFileMessage(int $convId, int $senderId): void
    {
        // 문서 첨부
        $docPath = 'messages/샘플-안내문.txt';
        if (! Storage::disk('public')->exists($docPath)) {
            Storage::disk('public')->put($docPath, "SmartLogis 채팅 첨부 샘플 문서\n용도: 파일 첨부 데모\n");
        }
        Message::create([
            'conversation_id' => $convId,
            'sender_id' => $senderId,
            'body' => '요청하신 안내 문서 첨부드립니다.',
            'file_path' => $docPath,
            'file_name' => '샘플-안내문.txt',
            'file_size' => Storage::disk('public')->size($docPath),
        ]);

        // 이미지 첨부(작은 PNG)
        $imgPath = 'messages/샘플-이미지.png';
        if (! Storage::disk('public')->exists($imgPath)) {
            $png = (string) base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAEAAAAAwCAIAAACX2m2wAAAAKUlEQVR4nO3BAQ0AAADCoP'.
                'dPbQ8HFAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAOB0Ay0AAAF6QpS0AAAAAElFTkSuQmCC'
            );
            Storage::disk('public')->put($imgPath, $png);
        }
        Message::create([
            'conversation_id' => $convId,
            'sender_id' => $senderId,
            'body' => null,
            'file_path' => $imgPath,
            'file_name' => '샘플-이미지.png',
            'file_size' => Storage::disk('public')->size($imgPath),
        ]);
    }

    /** @param  list<int>  $userIds */
    private function seedMessages(int $convId, array $userIds, int $seed): void
    {
        $count = 2 + ($seed % 3); // 2~4개
        $sampleCount = count(self::SAMPLES);
        $userCount = count($userIds);

        for ($i = 0; $i < $count; $i++) {
            Message::create([
                'conversation_id' => $convId,
                'sender_id' => $userIds[$i % $userCount],
                'body' => self::SAMPLES[($seed + $i) % $sampleCount],
            ]);
        }
    }
}
