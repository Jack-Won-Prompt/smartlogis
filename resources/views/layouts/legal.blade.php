@php $L = config('legal'); @endphp
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="index,follow">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <title>@yield('title', '법적 고지') · {{ $L['service_name'] }}</title>
    <style>
        :root{ --brand:#2551C4; --brand-700:#1d3fa0; --ink:#1e293b; --muted:#64748b;
               --line:#e2e8f0; --bg:#f8fafc; --card:#ffffff; }
        *{ box-sizing:border-box; }
        html{ -webkit-text-size-adjust:100%; }
        body{ margin:0; background:var(--bg); color:var(--ink);
              font-family:'맑은 고딕','Malgun Gothic',-apple-system,'Segoe UI',Roboto,sans-serif;
              line-height:1.7; font-size:15px; }
        a{ color:var(--brand); text-decoration:none; }
        a:hover{ text-decoration:underline; }

        header.site{ background:#0b1a33; }
        header.site .wrap{ max-width:860px; margin:0 auto; padding:16px 20px;
              display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; }
        header.site img{ height:30px; width:auto; display:block; background:#fff; padding:5px 10px; border-radius:10px; }
        nav.top a{ color:#c9d6f2; font-size:13px; margin-left:16px; white-space:nowrap; }
        nav.top a:hover{ color:#fff; }

        main{ max-width:860px; margin:0 auto; padding:40px 20px 24px; }
        .doc{ background:var(--card); border:1px solid var(--line); border-radius:16px;
              padding:40px 44px; box-shadow:0 1px 2px rgba(16,27,38,.04); }
        .eyebrow{ display:inline-block; font-size:12px; font-weight:700; letter-spacing:.02em;
              color:var(--brand); background:#eef2fb; border:1px solid #dbe4fa; border-radius:999px; padding:4px 12px; }
        h1{ font-size:28px; font-weight:800; margin:14px 0 6px; letter-spacing:-.01em; }
        .meta{ color:var(--muted); font-size:13px; margin:0 0 8px; }
        h2{ font-size:18px; font-weight:800; margin:32px 0 10px; padding-top:18px;
              border-top:1px solid var(--line); color:#0f213f; }
        h2:first-of-type{ border-top:none; padding-top:6px; }
        h3{ font-size:15px; font-weight:700; margin:18px 0 6px; }
        p{ margin:8px 0; }
        ul,ol{ margin:8px 0; padding-left:22px; }
        li{ margin:5px 0; }
        .muted{ color:var(--muted); }
        small{ color:var(--muted); }
        table{ width:100%; border-collapse:collapse; margin:12px 0; font-size:14px; }
        th,td{ border:1px solid var(--line); padding:9px 12px; text-align:left; vertical-align:top; }
        th{ background:#f1f5f9; font-weight:700; }
        .note{ background:#eff4ff; border:1px solid #d6e0fb; border-left:4px solid var(--brand);
              border-radius:10px; padding:14px 16px; margin:16px 0; font-size:14px; }
        .note b{ color:var(--brand-700); }
        .toc{ background:#f8fafc; border:1px solid var(--line); border-radius:12px; padding:16px 20px; margin:18px 0 6px; }
        .toc ol{ margin:6px 0 0; columns:2; }
        @media(max-width:640px){ .toc ol{ columns:1; } .doc{ padding:26px 18px; } h1{ font-size:23px; } }

        /* 폼 */
        .field{ margin:16px 0; }
        .field label{ display:block; font-weight:700; font-size:14px; margin-bottom:6px; }
        .field .req{ color:#dc2626; }
        input[type=text],input[type=email],input[type=tel],select,textarea{
              width:100%; border:1px solid var(--line); border-radius:10px; padding:11px 13px;
              font-size:15px; font-family:inherit; color:var(--ink); background:#fff; }
        textarea{ min-height:96px; resize:vertical; }
        input:focus,select:focus,textarea:focus{ outline:none; border-color:var(--brand);
              box-shadow:0 0 0 3px rgba(37,81,196,.15); }
        .radios{ display:flex; gap:10px; flex-wrap:wrap; }
        .radios label{ flex:1; min-width:200px; border:1px solid var(--line); border-radius:10px;
              padding:12px 14px; font-weight:600; font-size:14px; cursor:pointer; display:flex; gap:10px; align-items:flex-start; }
        .radios input{ margin-top:3px; }
        .check{ display:flex; gap:10px; align-items:flex-start; font-size:14px; }
        .btn{ display:inline-flex; align-items:center; gap:8px; background:var(--brand); color:#fff;
              border:none; border-radius:10px; padding:13px 22px; font-size:15px; font-weight:700;
              cursor:pointer; font-family:inherit; }
        .btn:hover{ background:var(--brand-700); }
        .err{ color:#dc2626; font-size:13px; margin-top:5px; }
        .errbox{ background:#fef2f2; border:1px solid #fecaca; border-radius:10px; padding:12px 14px;
              color:#b91c1c; font-size:14px; margin-bottom:16px; }
        .ok{ background:#ecfdf5; border:1px solid #a7f3d0; border-left:4px solid #10b981;
              border-radius:12px; padding:20px 22px; }
        .ok h2{ border:none; padding:0; margin:0 0 8px; color:#047857; }

        footer.site{ max-width:860px; margin:8px auto 40px; padding:20px; color:var(--muted); font-size:13px; }
        footer.site .links a{ margin-right:14px; }
        footer.site .co{ margin-top:10px; line-height:1.6; }
        @media print{ header.site,footer.site nav,.toc{ display:none; } body{ background:#fff; } .doc{ border:none; box-shadow:none; padding:0; } }
    </style>
</head>
<body>
    <header class="site">
        <div class="wrap">
            <a href="{{ url('/') }}"><img src="{{ asset('images/smartlogis_300x100.png') }}" alt="{{ $L['company'] }} {{ $L['service_name'] }}"></a>
            <nav class="top">
                <a href="{{ route('legal.privacy') }}">개인정보처리방침</a>
                <a href="{{ route('legal.terms') }}">이용약관</a>
                <a href="{{ route('account.delete') }}">계정 삭제 요청</a>
            </nav>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="site">
        <div class="links">
            <a href="{{ route('legal.privacy') }}">개인정보처리방침</a>
            <a href="{{ route('legal.terms') }}">이용약관</a>
            <a href="{{ route('account.delete') }}">계정 삭제 요청</a>
        </div>
        <div class="co">
            © {{ date('Y') }} {{ $L['company_legal'] }} · {{ $L['service_name'] }}<br>
            문의: {{ $L['email'] }}@if($L['phone']!=='02-0000-0000') · {{ $L['phone'] }}@endif
        </div>
    </footer>
</body>
</html>
