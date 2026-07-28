@extends('layouts.legal')
@section('title', '계정 삭제 요청')
@php $L = config('legal'); @endphp

@section('content')
<article class="doc">
    <span class="eyebrow">계정 삭제 요청</span>
    <h1>계정 및 데이터 삭제 요청</h1>
    <p class="meta">{{ $L['service_name'] }} 계정과 개인정보의 삭제를 요청하는 페이지입니다. 앱 설치·로그인 없이 요청할 수 있습니다.</p>

    @if(session('deletion_done'))
        <div class="ok">
            <h2>요청이 접수되었습니다</h2>
            <p style="margin:0">접수번호 <b>#{{ session('deletion_done') }}</b> · 입력하신 이메일로 본인 확인 후
            {{ $L['deletion_days'] }}일 이내에 처리해 드립니다. 처리 현황은 <b>{{ $L['email'] }}</b>로 문의해 주세요.</p>
        </div>
    @endif

    <h2>삭제되는 정보</h2>
    <ul>
        <li>계정 정보 — 이메일(로그인 ID), 이름, 비밀번호, 연락처</li>
        <li>로그인·접속 기록, 알림 및 메시지 내역</li>
        <li>기타 이용자를 식별할 수 있는 개인정보</li>
    </ul>

    <h2>보관되는 정보</h2>
    <p>아래 정보는 관련 법령에 따른 보존 의무가 있어 계정 삭제 후에도 정해진 기간 동안 <b>분리 보관</b>되며,
    보존 목적 외의 용도로는 이용되지 않고 기간이 지나면 지체 없이 파기합니다.</p>
    <table>
        <tr><th style="width:60%">보관 항목</th><th style="width:80px">기간</th><th>근거</th></tr>
        <tr><td>계약·거래 및 정산에 관한 기록</td><td>5년</td><td>전자상거래법</td></tr>
        <tr><td>소비자 불만 및 분쟁처리에 관한 기록</td><td>3년</td><td>전자상거래법</td></tr>
        <tr><td>로그인 접속 기록</td><td>3개월</td><td>통신비밀보호법</td></tr>
    </table>
    <p class="muted">※ 의료기기 유통 이력 등 관계 법령에 따라 별도 보존 의무가 있는 업무 데이터는 개인 식별 정보를
    최소화한 형태로 해당 법령이 정한 기간 동안 보존될 수 있습니다.</p>

    <div class="note">
        <b>참고</b> — {{ $L['service_name'] }}는 조직(본사·물류창고·병원·공급사) 단위 업무 서비스입니다.
        업무 연속성 확보를 위해 계정 삭제 전 소속 조직 관리자의 확인이 필요할 수 있으며, 본인 확인 후 처리됩니다.
        처리 기간은 접수 후 <b>{{ $L['deletion_days'] }}일 이내</b>입니다.
    </div>

    <h2>삭제 요청서 작성</h2>
    @if($errors->any())
        <div class="errbox">
            @foreach($errors->all() as $e)<div>· {{ $e }}</div>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('account.delete.submit') }}">
        @csrf
        <div class="field">
            <label>이름 <span class="req">*</span></label>
            <input type="text" name="name" value="{{ old('name') }}" required maxlength="100" placeholder="가입 시 등록한 이름">
        </div>
        <div class="field">
            <label>가입 이메일 <span class="req">*</span></label>
            <input type="email" name="email" value="{{ old('email') }}" required maxlength="190" placeholder="계정 이메일(로그인 ID)">
        </div>
        <div class="field">
            <label>연락처 <small>(선택)</small></label>
            <input type="tel" name="phone" value="{{ old('phone') }}" maxlength="40" placeholder="본인 확인 시 연락받을 번호">
        </div>
        <div class="field">
            <label>요청 유형 <span class="req">*</span></label>
            <div class="radios">
                <label>
                    <input type="radio" name="request_type" value="ACCOUNT" {{ old('request_type','ACCOUNT')==='ACCOUNT' ? 'checked' : '' }}>
                    <span>계정 전체 삭제<br><small class="muted">계정과 개인정보를 모두 삭제</small></span>
                </label>
                <label>
                    <input type="radio" name="request_type" value="DATA" {{ old('request_type')==='DATA' ? 'checked' : '' }}>
                    <span>일부 데이터 삭제<br><small class="muted">계정은 유지, 특정 개인정보만 삭제</small></span>
                </label>
            </div>
        </div>
        <div class="field">
            <label>요청 사유 <small>(선택)</small></label>
            <textarea name="reason" maxlength="2000" placeholder="'일부 데이터 삭제'인 경우 삭제를 원하는 항목을 적어주세요.">{{ old('reason') }}</textarea>
        </div>
        <div class="field">
            <label class="check">
                <input type="checkbox" name="agree" value="1" {{ old('agree') ? 'checked' : '' }}>
                <span>위 <b>삭제되는 정보</b> 및 <b>보관되는 정보</b> 안내를 확인했으며, 계정/데이터 삭제 요청에 동의합니다. <span class="req">*</span></span>
            </label>
        </div>
        <button type="submit" class="btn">삭제 요청 제출</button>
    </form>

    <h2>이메일로 요청하기</h2>
    <p>위 양식 대신 이메일로도 요청하실 수 있습니다. 아래 주소로 <b>가입 이메일과 요청 내용</b>을 보내주세요.</p>
    <p><a href="mailto:{{ $L['email'] }}?subject=계정 삭제 요청">{{ $L['email'] }}</a>@if($L['phone']!=='02-0000-0000') · 고객센터 {{ $L['phone'] }}@endif</p>
</article>
@endsection
