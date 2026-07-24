<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <title>@yield('code') · SmartLogis</title>
    <style>
        :root { --brand: #2551c4; --brand2: #2d6ae0; --ink: #0e1a2b; --muted: #6b7a88; --line: #e8edf1; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; }
        body {
            font-family: 'Pretendard Variable', Pretendard, -apple-system, 'Malgun Gothic', system-ui, sans-serif;
            color: var(--ink); background: radial-gradient(1200px 600px at 50% -10%, #eef3fe 0%, #f7f9fb 45%, #f4f6f8 100%);
            display: flex; align-items: center; justify-content: center; padding: 24px; -webkit-font-smoothing: antialiased;
        }
        .card {
            width: 100%; max-width: 440px; background: #fff; border: 1px solid var(--line);
            border-radius: 22px; padding: 44px 40px 36px; text-align: center;
            box-shadow: 0 20px 60px -20px rgba(37, 81, 196, .18), 0 2px 8px rgba(16, 27, 38, .04);
        }
        .icon {
            width: 60px; height: 60px; margin: 0 auto 20px; border-radius: 18px;
            background: linear-gradient(135deg, var(--brand2), var(--brand));
            display: grid; place-items: center; box-shadow: 0 8px 20px -6px rgba(37, 81, 196, .5);
        }
        .code {
            font-size: 76px; font-weight: 800; line-height: 1; letter-spacing: -2px;
            background: linear-gradient(135deg, var(--brand2), var(--brand));
            -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
        }
        h1 { margin-top: 14px; font-size: 20px; font-weight: 700; letter-spacing: -.4px; }
        p.msg { margin-top: 10px; font-size: 14px; line-height: 1.6; color: var(--muted); }
        .actions { margin-top: 26px; display: flex; gap: 8px; justify-content: center; }
        a.btn {
            display: inline-flex; align-items: center; gap: 7px; text-decoration: none;
            padding: 11px 20px; border-radius: 12px; font-size: 14px; font-weight: 600; transition: .15s;
        }
        a.primary { background: var(--brand); color: #fff; box-shadow: 0 6px 16px -6px rgba(37, 81, 196, .5); }
        a.primary:hover { filter: brightness(1.06); }
        a.ghost { color: var(--muted); border: 1px solid var(--line); background: #fff; }
        a.ghost:hover { background: #f7f9fb; color: var(--ink); }
        .brand { margin-top: 22px; font-size: 12px; color: #9aa7b3; letter-spacing: .2px; }
        .brand b { color: var(--brand); font-weight: 700; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">
            <svg width="30" height="30" viewBox="0 0 64 64" fill="none" stroke="#fff" stroke-width="3.4" stroke-linejoin="round" stroke-linecap="round">
                <path d="M32 15 51 25.5v13L32 49 13 38.5v-13L32 15Z"/><path d="M13 25.5 32 36l19-10.5M32 36v13"/>
            </svg>
        </div>
        <div class="code">@yield('code')</div>
        <h1>@yield('title')</h1>
        <p class="msg">@yield('message')</p>
        <div class="actions">
            <a class="btn primary" href="{{ url('/') }}">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 10 9-7 9 7v9a2 2 0 0 1-2 2h-4v-6H9v6H5a2 2 0 0 1-2-2Z"/></svg>
                홈으로
            </a>
            <a class="btn ghost" href="javascript:history.back()">이전 페이지</a>
        </div>
        <p class="brand">SmartLogis · <b>삼에스메디컬</b> 간납 물류 관제</p>
    </div>
</body>
</html>
