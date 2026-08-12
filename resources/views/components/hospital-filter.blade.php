{{-- 병원 필터 — 병원을 넘나드는 역할(HQ/창고/라이프/공급사)에게만 노출.
     병원 계정은 이미 자기 병원으로 스코프되어 있으므로 필터를 그리지 않는다.
     사용: <x-hospital-filter :roles="['HQ','WAREHOUSE','LIFE']" />  (id 기본 f-hospital) --}}
@props(['id' => 'f-hospital', 'label' => '병원', 'roles' => ['HQ', 'WAREHOUSE', 'LIFE']])
@php
    $me = auth()->user();
    $show = $me && in_array($me->role->value, (array) $roles, true);
    $hospitals = $show
        ? \App\Models\Organization::query()
            ->where('org_type', \App\Enums\OrgType::HOSPITAL)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
        : collect();
@endphp
@if($hospitals->isNotEmpty())
<div>
    <label class="mb-1 block text-xs font-medium text-ink-500">{{ $label }}</label>
    <select id="{{ $id }}" class="rounded-lg border-line bg-surface-1 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-400/25">
        <option value="">전체</option>
        @foreach($hospitals as $h)<option value="{{ $h->id }}">{{ $h->name }}</option>@endforeach
    </select>
</div>
@endif
