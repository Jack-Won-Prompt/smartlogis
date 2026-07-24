#!/usr/bin/env bash
#
# SmartLogis 운영 배포 스크립트
# 사용법:  bash deploy.sh            # master 브랜치 기준 배포
#          BRANCH=main bash deploy.sh
#
# 하는 일: git pull → 의존성 설치 → 자산 빌드 → 마이그레이션 → 캐시 재생성
# 주의:   .env / vendor / node_modules / public/build 는 git에 없으므로 이 스크립트가 재생성합니다.
#         .env 는 서버에 직접 관리하세요(이 스크립트가 건드리지 않음).
#
set -euo pipefail

BRANCH="${BRANCH:-master}"

# 스크립트가 있는 디렉터리(=프로젝트 루트)로 이동
cd "$(dirname "$0")"

echo "▶ SmartLogis 배포 시작 (브랜치: ${BRANCH})"

# .env 존재 확인 — 없으면 중단(첫 배포는 README/A단계 참고)
if [ ! -f .env ]; then
  echo "✖ .env 가 없습니다. 최초 1회는 .env 를 먼저 구성하세요." >&2
  exit 1
fi

# 배포 실패 시에도 유지보수 모드를 반드시 해제
cleanup() { php artisan up >/dev/null 2>&1 || true; }
trap cleanup EXIT

echo "▶ 유지보수 모드 진입"
php artisan down --render="errors::503" --retry=15 || true

echo "▶ 최신 코드 가져오기"
git fetch --prune origin
git checkout "${BRANCH}"
git pull --ff-only origin "${BRANCH}"

echo "▶ PHP 의존성 설치 (composer)"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

echo "▶ 프런트 자산 빌드 (npm) — public/build 재생성"
if command -v npm >/dev/null 2>&1; then
  npm ci
  npm run build
else
  echo "⚠ npm 이 없어 자산 빌드를 건너뜁니다. public/build 를 별도 전송했는지 확인하세요." >&2
fi

echo "▶ DB 마이그레이션"
php artisan migrate --force

echo "▶ 캐시 재생성"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache || true

echo "▶ 큐 워커 재시작(있으면)"
php artisan queue:restart || true

echo "▶ storage 심볼릭 링크 확인"
php artisan storage:link >/dev/null 2>&1 || true

echo "▶ 유지보수 모드 해제"
php artisan up
trap - EXIT

echo "✔ 배포 완료 — $(git rev-parse --short HEAD) $(git log -1 --pretty=%s)"
