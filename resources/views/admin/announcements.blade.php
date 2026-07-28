@php
    use App\Enums\OrgType;
    use App\Enums\Severity;
    $roleOptions = array_filter(OrgType::cases(), fn ($t) => $t !== OrgType::HQ); // HQ 는 모든 알림 수신
    $orgsByType = $organizations->groupBy(fn ($o) => $o->org_type->value ?? (string) $o->org_type);
@endphp

<x-app-layout title="공지 발송" breadcrumb="관리 / 공지 발송">
    <x-page-header title="공지사항 발송"
        subtitle="앱 사용자에게 공지를 보냅니다. 저장 시 대상자의 모바일 앱으로 푸시(FCM) 알림이 발송되고 알림 센터에도 표시됩니다." />

    @if(session('announced'))
        <div class="mt-6 flex items-start gap-2 rounded-xl border border-ok-600/30 bg-ok-100 px-4 py-3 text-sm text-ok-700">
            <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
            <span>{{ session('announced') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="mt-6 rounded-xl border border-crit-500/30 bg-crit-100 px-4 py-3 text-sm text-crit-600">
            @foreach($errors->all() as $e)<div>· {{ $e }}</div>@endforeach
        </div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-[1.4fr_1fr]">
        {{-- ── 작성 폼 ─────────────────────────────── --}}
        <form method="POST" action="{{ route('admin.announcements.store') }}"
              x-data="{ mode: '{{ old('target_mode', 'all') }}', send() { window.confirmDialog({ title:'공지 발송', message:'선택한 대상에게 공지를 발송할까요? 모바일 앱 푸시가 전송됩니다.', confirmText:'발송', tone:'brand' }).then(ok => { if(ok) this.$refs.form.submit(); }); } }"
              x-ref="form" @submit.prevent="send()"
              class="rounded-xl border border-line bg-white p-5 shadow-sm">
            @csrf

            <label class="mb-1 block text-xs font-medium text-ink-500">제목 <span class="text-crit-500">*</span></label>
            <input type="text" name="title" value="{{ old('title') }}" maxlength="120" required
                   placeholder="예) 시스템 정기 점검 안내"
                   class="mb-4 w-full rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">

            <label class="mb-1 block text-xs font-medium text-ink-500">내용</label>
            <textarea name="message" rows="4" maxlength="2000"
                      placeholder="공지 내용을 입력하세요."
                      class="mb-4 w-full rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">{{ old('message') }}</textarea>

            <div class="mb-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-ink-500">중요도</label>
                    <select name="severity" class="w-full rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
                        @foreach(Severity::cases() as $s)
                            <option value="{{ $s->value }}" @selected(old('severity','INFO')===$s->value)>{{ $s->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-ink-500">연결 링크 <span class="text-ink-300">(선택)</span></label>
                    <input type="text" name="link_url" value="{{ old('link_url') }}" maxlength="255"
                           placeholder="/notifications"
                           class="w-full rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
                </div>
            </div>

            {{-- 발송 대상 --}}
            <label class="mb-1 block text-xs font-medium text-ink-500">발송 대상 <span class="text-crit-500">*</span></label>
            <div class="mb-3 grid gap-2 sm:grid-cols-3">
                @foreach(['all'=>'전체 사용자','role'=>'역할별','org'=>'조직별'] as $val=>$lab)
                    <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-line px-3 py-2 text-sm font-medium"
                           :class="mode==='{{ $val }}' ? 'border-brand-500 bg-brand-50 text-brand-700' : 'text-ink-600'">
                        <input type="radio" name="target_mode" value="{{ $val }}" x-model="mode" class="text-brand-600">
                        {{ $lab }}
                    </label>
                @endforeach
            </div>

            {{-- 역할 선택 --}}
            <div x-show="mode==='role'" x-cloak class="mb-4 rounded-lg border border-line bg-surface-1 p-3">
                <p class="mb-2 text-xs text-ink-400">받을 역할을 선택하세요. (본사는 모든 공지를 자동 수신합니다)</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($roleOptions as $r)
                        <label class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-white px-3 py-1.5 text-sm">
                            <input type="checkbox" name="roles[]" value="{{ $r->value }}"
                                   @checked(collect(old('roles',[]))->contains($r->value)) class="text-brand-600">
                            {{ $r->label() }}
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- 조직 선택 --}}
            <div x-show="mode==='org'" x-cloak class="mb-4 rounded-lg border border-line bg-surface-1 p-3">
                <p class="mb-2 text-xs text-ink-400">받을 조직을 선택하세요.</p>
                <div class="max-h-56 space-y-3 overflow-y-auto">
                    @foreach($orgsByType as $type => $orgs)
                        <div>
                            <p class="mb-1 text-[11px] font-semibold text-ink-400">{{ OrgType::tryFrom($type)?->label() ?? $type }}</p>
                            <div class="grid gap-1 sm:grid-cols-2">
                                @foreach($orgs as $o)
                                    <label class="inline-flex items-center gap-1.5 rounded-md bg-white px-2 py-1 text-sm">
                                        <input type="checkbox" name="org_ids[]" value="{{ $o->id }}"
                                               @checked(collect(old('org_ids',[]))->contains($o->id)) class="text-brand-600">
                                        {{ $o->name }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-2 flex items-center justify-end gap-2">
                <button type="submit" class="btn-primary">공지 발송</button>
            </div>
        </form>

        {{-- ── 발송 이력 ─────────────────────────────── --}}
        <div class="rounded-xl border border-line bg-white p-5 shadow-sm">
            <h3 class="mb-3 text-sm font-bold text-ink-900">최근 발송 공지</h3>
            <div class="space-y-2">
                @forelse($recent as $n)
                    <div class="rounded-lg border border-line/70 p-3">
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-sm font-semibold text-ink-900">{{ $n->title }}</p>
                            <x-status-badge :status="$n->severity" class="!py-0.5 shrink-0" />
                        </div>
                        @if($n->message)<p class="mt-1 line-clamp-2 text-xs text-ink-500">{{ $n->message }}</p>@endif
                        <div class="mt-1.5 flex items-center gap-2 text-[11px] text-ink-300">
                            <span>대상:
                                @if($n->target_org_id){{ $n->targetOrg?->name ?? '조직' }}
                                @elseif($n->target_role){{ $n->target_role->label() }}
                                @else 전체 @endif
                            </span>
                            <span>·</span>
                            <span>{{ optional($n->created_at)->timezone('Asia/Seoul')?->format('Y-m-d H:i') }}</span>
                        </div>
                    </div>
                @empty
                    <p class="py-8 text-center text-sm text-ink-300">발송한 공지가 없습니다.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
