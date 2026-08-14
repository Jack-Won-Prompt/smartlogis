<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ $docNo }} · SmartLogis</title>
    <style>
        *{ box-sizing:border-box; }
        body{ margin:0; background:#f1f5f9; font-family:'맑은 고딕','Malgun Gothic',sans-serif; color:#1e293b; }
        .bar{ position:sticky; top:0; display:flex; align-items:center; justify-content:space-between; gap:12px; padding:12px 20px; background:#0b1a33; color:#fff; }
        .bar h1{ font-size:15px; margin:0; font-weight:700; }
        .bar button{ background:#2551c4; color:#fff; border:none; border-radius:8px; padding:8px 16px; font-size:13px; font-weight:700; cursor:pointer; }
        .sheet{ max-width:820px; margin:16px auto; background:#fff; border:1px solid #cbd5e1; border-radius:6px; padding:28px 32px 36px; }
        .head{ display:flex; align-items:flex-start; justify-content:space-between; gap:20px; border-bottom:2px solid #0b1a33; padding-bottom:14px; }
        .head h2{ font-size:24px; margin:0 0 4px; letter-spacing:4px; }
        .head .docno{ font-family:monospace; font-size:15px; font-weight:700; color:#2551c4; margin:0 0 10px; }
        .meta{ font-size:12.5px; border-collapse:collapse; }
        .meta td{ padding:2px 0; }
        .meta td.k{ color:#64748b; padding-right:14px; white-space:nowrap; }
        .qr{ text-align:center; flex-shrink:0; }
        .qr img{ width:120px; height:120px; display:block; }
        .qr span{ font-family:monospace; font-size:11px; color:#334155; }
        .route{ display:flex; align-items:center; gap:14px; margin:18px 0; }
        .route .box{ flex:1; border:1px solid #cbd5e1; border-radius:8px; padding:10px 14px; }
        .route .box .lbl{ font-size:11px; color:#64748b; }
        .route .box .val{ font-size:16px; font-weight:700; margin-top:2px; }
        .route .arrow{ font-size:20px; color:#2551c4; }
        table.items{ width:100%; border-collapse:collapse; margin-top:6px; font-size:13px; }
        table.items th{ background:#f0f3f7; border:1px solid #cbd5e1; padding:7px 8px; text-align:left; font-size:12px; }
        table.items td{ border:1px solid #cbd5e1; padding:7px 8px; }
        table.items td.r, table.items th.r{ text-align:right; }
        table.items .mono{ font-family:monospace; font-size:12px; }
        table.items tfoot td{ background:#f8fafc; font-weight:700; }
        .sign{ display:flex; justify-content:flex-end; gap:34px; margin-top:34px; font-size:12.5px; color:#334155; }
        .sign .slot{ text-align:center; }
        .sign .line{ width:130px; border-bottom:1px solid #94a3b8; height:34px; }
        .foot{ margin-top:22px; text-align:center; font-size:11px; color:#94a3b8; }
        @media print{ .bar{ display:none; } body{ background:#fff; } .sheet{ margin:0; border:none; border-radius:0; } }
    </style>
</head>
<body>
    <div class="bar">
        <h1>{{ $title }} — {{ $docNo }}</h1>
        <button onclick="window.print()">🖨 인쇄</button>
    </div>

    <div class="sheet">
        <div class="head">
            <div>
                <h2>{{ $title }}</h2>
                <p class="docno">{{ $docNo }}</p>
                <table class="meta">
                    @foreach($meta as [$k, $v])
                        <tr><td class="k">{{ $k }}</td><td>{{ $v }}</td></tr>
                    @endforeach
                </table>
            </div>
            <div class="qr">
                <img src="{{ $qr }}" alt="QR">
                <span>{{ $docNo }}</span>
            </div>
        </div>

        <div class="route">
            <div class="box"><div class="lbl">{{ $fromLabel }}</div><div class="val">{{ $fromName }}</div></div>
            <div class="arrow">→</div>
            <div class="box"><div class="lbl">{{ $toLabel }}</div><div class="val">{{ $toName }}</div></div>
        </div>

        <table class="items">
            <thead>
                <tr><th style="width:34px" class="r">No</th><th>제품코드</th><th>제품명</th><th>Lot</th><th>유통기한</th><th class="r">수량</th></tr>
            </thead>
            <tbody>
                @forelse($items as $i => $it)
                    <tr>
                        <td class="r">{{ $i + 1 }}</td>
                        <td class="mono">{{ $it['code'] }}</td>
                        <td>{{ $it['name'] }}</td>
                        <td class="mono">{{ $it['lot'] }}</td>
                        <td class="mono">{{ $it['expiry'] }}</td>
                        <td class="r">{{ number_format($it['qty']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:24px">품목이 없습니다.</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr><td colspan="5" class="r">합계 수량</td><td class="r">{{ number_format(collect($items)->sum('qty')) }}</td></tr>
            </tfoot>
        </table>

        <div class="sign">
            <div class="slot">{{ $signLeft }}<div class="line"></div></div>
            <div class="slot">{{ $signRight }}<div class="line"></div></div>
        </div>

        <div class="foot">SmartLogis · 출력일 {{ now()->timezone('Asia/Seoul')->format('Y-m-d H:i') }} · 본 지시서의 QR 은 문서번호({{ $docNo }})를 담고 있습니다.</div>
    </div>
</body>
</html>
