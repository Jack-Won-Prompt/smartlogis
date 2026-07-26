@php
    /** @var \App\Models\Message $m */
    $mine = (int) $m->sender_id === (int) $meId;
@endphp
<div data-msg="{{ $m->id }}" class="group flex {{ $mine ? 'justify-end' : 'justify-start' }}">
    @unless($mine)
        <span class="mr-2 mt-1 grid h-7 w-7 shrink-0 place-items-center self-end rounded-full bg-brand-gradient text-[10px] font-bold text-white">{{ mb_substr($m->sender?->name ?? '?', 0, 1) }}</span>
    @endunless
    <div class="max-w-[70%]">
        <div data-bubble class="relative rounded-2xl px-3 py-2 text-sm {{ $mine ? 'bg-brand-600 text-white' : 'border border-line bg-white text-ink-800' }}">
            @if($m->isDeleted())
                <span class="text-sm italic {{ $mine ? 'text-white/70' : 'text-ink-300' }}">삭제된 메시지</span>
            @else
                @unless($mine)
                    <div class="mb-0.5 text-[11px] font-semibold text-brand-600">{{ $m->sender?->name }}</div>
                @endunless
                @if($m->replyTo)
                    <div class="mb-1 rounded bg-black/5 px-2 py-1 text-[11px] opacity-80"><b>{{ $m->replyTo->sender?->name }}</b> {{ \Illuminate\Support\Str::limit($m->replyTo->body, 60) }}</div>
                @endif
                @if($m->file_path)
                    @if($m->isImage())
                        <a href="{{ $m->fileUrl() }}" target="_blank"><img src="{{ $m->fileUrl() }}" class="max-h-52 rounded-lg" alt="{{ $m->file_name }}"></a>
                    @else
                        <a href="{{ $m->fileUrl() }}" target="_blank" class="flex items-center gap-1 underline">📎 {{ $m->file_name }} <span class="text-[10px] opacity-70">({{ $m->formattedSize() }})</span></a>
                    @endif
                @endif
                @if($m->body)
                    <div data-body class="whitespace-pre-wrap break-words">{{ $m->body }}</div>
                @endif
                <span data-edited class="{{ $m->isEdited() ? '' : 'hidden' }} ml-1 text-[10px] {{ $mine ? 'text-white/60' : 'text-ink-300' }}">(수정됨)</span>

                {{-- 본인 메시지: 답장/수정/삭제 --}}
                <div class="pointer-events-none absolute -top-3 {{ $mine ? 'left-0 -translate-x-full pr-1' : 'right-0 translate-x-full pl-1' }} opacity-0 transition-opacity group-hover:pointer-events-auto group-hover:opacity-100">
                    <div class="flex items-center gap-0.5 rounded-lg border border-line bg-white p-0.5 shadow-sm">
                        <button @click="setReply({{ $m->id }}, @js($m->sender?->name), @js(\Illuminate\Support\Str::limit($m->body ?: $m->file_name, 60)))" class="grid h-6 w-6 place-items-center rounded text-ink-400 hover:bg-surface-2" title="답장">↩</button>
                        @if($mine)
                            <button @click="editMsg({{ $m->id }})" class="grid h-6 w-6 place-items-center rounded text-ink-400 hover:bg-surface-2" title="수정">✎</button>
                            <button @click="delMsg({{ $m->id }})" class="grid h-6 w-6 place-items-center rounded text-crit-500 hover:bg-crit-100" title="삭제">🗑</button>
                        @endif
                    </div>
                </div>
            @endif
        </div>
        <div class="mt-0.5 text-[10px] text-ink-300 {{ $mine ? 'text-right' : '' }}">{{ optional($m->created_at)->timezone('Asia/Seoul')?->format('H:i') }}</div>
    </div>
</div>
