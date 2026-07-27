<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 모바일 앱 버전 확인.
 *
 * 플레이스토어 배포가 아니라 스토어의 자동 업데이트가 없다. 앱이 시작·복귀할 때
 * 이 엔드포인트로 최신 버전을 물어보고 자기 versionCode 와 비교한다.
 *
 * **인증 없이** 접근할 수 있어야 한다. 강제 업데이트 대상 구버전은 로그인 자체가
 * 실패할 수 있는데(예: 인증 응답 스키마 변경), 그 상태에서도 "업데이트하세요" 는
 * 띄울 수 있어야 하기 때문이다. 공개되는 값은 버전 번호와 이미 공개된 APK 주소뿐이다.
 */
class AppVersionController extends ApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        $cfg = config('mobile.android');

        $latest = (int) $cfg['latest_version'];
        $min = (int) $cfg['min_version'];

        // 앱이 자기 버전과 ABI 를 알려주면 맞춤 응답을 준다(없어도 동작).
        $current = $request->integer('version_code');
        $abi = $request->string('abi')->toString();

        // ABI 별 APK 가 있으면 그것을 준다 — universal 대비 절반 크기.
        $downloadUrl = $cfg['download_urls'][$abi] ?? $cfg['download_url'];

        $updateAvailable = $current > 0 && $current < $latest;
        $updateRequired = $current > 0 && $current < $min;

        return response()->json([
            'platform' => 'android',
            'latest_version' => $latest,
            'latest_version_name' => $cfg['latest_version_name'],
            'min_version' => $min,
            'download_url' => $downloadUrl,
            'install_page_url' => $cfg['install_page_url'],
            'release_notes' => $cfg['release_notes'],

            // 판단은 서버가 한다. 앱은 이 두 불리언만 보고 UI 를 정한다.
            'update_available' => $updateAvailable,
            'update_required' => $updateRequired,

            'message' => $updateRequired
                ? '필수 업데이트가 있습니다. 계속 사용하려면 최신 버전을 설치해 주세요.'
                : ($updateAvailable ? '새 버전이 있습니다.' : null),
        ]);
    }
}
