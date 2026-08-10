<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · SmartLogis</title>
    <style>
        *{ box-sizing:border-box; }
        body{ margin:0; background:#f1f5f9; font-family:'맑은 고딕','Malgun Gothic',sans-serif; color:#1e293b; }
        .bar{ position:sticky; top:0; display:flex; align-items:center; justify-content:space-between;
              gap:12px; padding:12px 20px; background:#0b1a33; color:#fff; }
        .bar h1{ font-size:15px; margin:0; font-weight:700; }
        .bar button{ background:#2551c4; color:#fff; border:none; border-radius:8px; padding:8px 16px; font-size:13px; font-weight:700; cursor:pointer; }
        .sheet{ max-width:900px; margin:16px auto; display:grid; grid-template-columns:repeat(3,1fr); gap:10px; padding:0 16px 40px; }
        .label{ background:#fff; border:1px solid #cbd5e1; border-radius:8px; padding:10px; display:flex; gap:10px; align-items:center; page-break-inside:avoid; }
        .label img{ width:96px; height:96px; flex-shrink:0; }
        .meta{ min-width:0; }
        .meta .code{ font-size:11px; color:#64748b; font-family:monospace; }
        .meta .name{ font-size:13px; font-weight:700; margin:2px 0; word-break:break-all; }
        .meta .lot{ font-size:12px; }
        .meta .lot b{ font-family:monospace; }
        .meta .exp{ font-size:11px; color:#b45309; margin-top:2px; }
        .empty{ text-align:center; color:#94a3b8; padding:60px; }
        @media print{ .bar{ display:none; } body{ background:#fff; } .sheet{ margin:0; } .label{ border-color:#94a3b8; } }
    </style>
</head>
<body>
    <div class="bar">
        <h1>{{ $title }} — {{ count($labels) }}건</h1>
        <button onclick="window.print()">🖨 인쇄</button>
    </div>
    @if(count($labels) === 0)
        <p class="empty">라벨을 생성할 항목이 없습니다. (출고는 피킹으로 LOT 이 배정된 뒤 생성됩니다.)</p>
    @else
        <div class="sheet">
            @foreach($labels as $l)
                <div class="label">
                    <img src="{{ $l['qr'] }}" alt="QR">
                    <div class="meta">
                        <div class="code">{{ $l['product_code'] }}</div>
                        <div class="name">{{ $l['product_name'] }}</div>
                        <div class="lot">Lot <b>{{ $l['lot_no'] }}</b></div>
                        @if($l['expiry'])<div class="exp">EXP {{ $l['expiry'] }}</div>@endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</body>
</html>
