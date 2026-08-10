@php $L = ['brand' => '#2551C4']; @endphp
<!DOCTYPE html>
<html lang="ko">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;background:#f1f5f9;font-family:'Malgun Gothic',sans-serif;color:#1e293b;">
    <div style="max-width:560px;margin:24px auto;background:#fff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;">
        <div style="background:#0b1a33;padding:16px 22px;color:#fff;font-weight:700;">SmartLogis 알림</div>
        <div style="padding:22px;">
            <p style="display:inline-block;margin:0 0 10px;font-size:12px;font-weight:700;color:{{ $L['brand'] }};background:#eef2fb;border:1px solid #dbe4fa;border-radius:999px;padding:3px 10px;">{{ $n->noti_type->label() }} · {{ $n->severity->label() }}</p>
            <h1 style="font-size:18px;margin:6px 0 10px;">{{ $n->title }}</h1>
            @if($n->message)<p style="font-size:14px;line-height:1.7;color:#334155;white-space:pre-wrap;">{{ $n->message }}</p>@endif
            @if($n->link_url)
                <p style="margin-top:18px;"><a href="{{ url($n->link_url) }}" style="display:inline-block;background:{{ $L['brand'] }};color:#fff;text-decoration:none;border-radius:8px;padding:10px 18px;font-size:14px;font-weight:700;">확인하기</a></p>
            @endif
        </div>
        <div style="padding:14px 22px;border-top:1px solid #e2e8f0;color:#94a3b8;font-size:12px;">© {{ date('Y') }} 삼에스메디컬 · SmartLogis</div>
    </div>
</body>
</html>
