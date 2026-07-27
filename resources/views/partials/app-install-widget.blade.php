{{-- 좌하단 "모바일 앱 설치" 위젯 --}}
{{--
    플레이스토어를 거치지 않는 배포라 앱을 찾을 경로가 이 사이트뿐이다.
    로그인 전 화면(랜딩·로그인)에만 붙인다 — 업무 화면(워크스페이스)은 표가 빽빽해서
    고정 위젯이 데이터를 가린다. 설치는 로그인 전에 끝나는 일이기도 하다.

    데스크톱: QR (폰으로 찍어 설치)
    모바일:  QR 대신 설치 페이지 링크 — 자기 화면의 QR 은 찍을 수 없다.

    색은 DESIGN.md 브랜드 딥 틸을 따른다.
--}}
<aside class="aiw" id="aiw" aria-label="모바일 앱 설치">
    <button type="button" class="aiw-close" id="aiw-close" aria-label="닫기">&times;</button>

    <p class="aiw-title">모바일 앱 설치</p>

    {{-- 데스크톱 — QR 상시 노출 --}}
    <a class="aiw-qr" href="{{ url('/app/') }}">
        <img src="{{ asset('app/install-qr.png') }}" alt="앱 설치 페이지 QR 코드" width="128" height="128">
    </a>
    <p class="aiw-hint">휴대폰으로 찍으세요</p>

    {{-- 모바일 — 바로 이동 --}}
    <a class="aiw-cta" href="{{ url('/app/') }}">앱 설치</a>
</aside>

<style>
    .aiw {
        position: fixed; left: 20px; bottom: 20px; z-index: 40;
        width: 164px; padding: 14px 12px 12px; text-align: center;
        background: #fff; border: 1px solid #DDE4EA; border-radius: 12px;
        box-shadow: 0 10px 30px rgba(16, 27, 38, .12);
        font-family: inherit;
    }
    .aiw.is-hidden { display: none; }

    .aiw-close {
        position: absolute; top: 4px; right: 6px;
        border: 0; background: transparent; color: #94A5B3;
        font-size: 18px; line-height: 1; cursor: pointer; padding: 2px 4px;
    }
    .aiw-close:hover { color: #101B26; }

    .aiw-title { margin: 0 0 10px; font-size: 12.5px; font-weight: 700; color: #101B26; }

    .aiw-qr { display: block; }
    .aiw-qr img { width: 128px; height: 128px; display: block; margin: 0 auto; border-radius: 6px; }

    .aiw-hint { margin: 8px 0 0; font-size: 11px; color: #5E7080; }

    .aiw-cta {
        display: none; padding: 11px 16px;
        background: #0E7473; color: #fff; font-weight: 700; font-size: 14px;
        border-radius: 6px; text-decoration: none;
    }

    /* 모바일: 자기 화면의 QR 은 찍을 수 없으므로 링크 버튼으로 바꾸고 카드를 줄인다. */
    @media (max-width: 720px) {
        .aiw { left: 12px; bottom: 12px; width: auto; padding: 10px; }
        .aiw-title, .aiw-qr, .aiw-hint { display: none; }
        .aiw-cta { display: block; }
        .aiw-close { display: none; }
    }

    /* 세로가 짧은 화면(가로 모드 등)에서는 QR 이 화면을 다 덮는다 — 링크로 대체. */
    @media (max-height: 560px) {
        .aiw { width: auto; padding: 10px; }
        .aiw-title, .aiw-qr, .aiw-hint { display: none; }
        .aiw-cta { display: block; }
    }
</style>

<script>
(function () {
    var root = document.getElementById('aiw');
    if (!root) return;

    document.getElementById('aiw-close').addEventListener('click', function () {
        // 이 페이지에서만 감춘다(저장하지 않음) — 다음 페이지에서 다시 보인다.
        root.classList.add('is-hidden');
    });
})();
</script>
