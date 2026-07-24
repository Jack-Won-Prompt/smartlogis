# CLAUDE.md — SmartLogis 간납 물류 시스템 (Laravel)

이 문서는 Claude Code가 SmartLogis 시스템을 구축할 때 참조하는 프로젝트 사양서입니다.
모든 코드 작성 시 이 문서의 규칙과 데이터 모델을 따릅니다.
**UI/UX는 별도 문서 `DESIGN.md`(디자인 시스템 "Control Tower")를 반드시 함께 읽고 그대로 따른다.**

---

## 1. 프로젝트 개요

**SmartLogis**는 의료 제품(의료기기/소모품)의 간납(간접납품) 유통을 관리하는 웹 시스템이다.

### 비즈니스 모델 (위탁판매/Consignment)
- 삼에스가 공급사 제품을 전국 거점병원 선납창고에 **미리 입고(선납)** 시킨다.
- 병원은 제품을 사용한 후, **사용분(품목/Lot/수량/금액)을 본사에 전송**한다.
- 본사가 사용분을 **승인하면 해당 병원 재고가 차감**되고 정산(매출) 대상이 된다.
- 공급사는 자사 제품의 병원별 재고를 조회하고, 부족 시 삼에스 물류창고에 납품한다.
- 물류창고는 안전재고 미달 병원에 제품을 출고/배송한다.
- 병원별 × 품목별 **안전재고** 기준으로 자동 보충 알림이 발생한다.

### 물류 흐름
```
공급사 → [입고] → 삼에스 물류창고 → [출고/배송] → 거점병원 선납창고
                                                        ↓ 사용
본사 정산 ← [승인] ← 사용분 전송 ← ─ ─ ─ ─ ─ ─ ─ ─ ─ ─ 병원
```

---

## 2. 사용자 역할 (4종)

| 역할 코드 | 역할 | 데이터 접근 범위 |
|---|---|---|
| `HQ` | 삼에스 본사 | 전체 데이터. 사용분 승인, 정산, 마스터 관리 |
| `WAREHOUSE` | 삼에스 물류창고 | 창고 입출고, 배송, 창고 재고 |
| `HOSPITAL` | 거점병원 선납창고 | **자기 병원** 재고/사용분/입고만 |
| `SUPPLIER` | 공급사 | **자사 제품**의 재고/납품/발주만 |

> 권한은 Role + 소속 조직(org_id) 이중 필터. 모든 조회는 서버단에서 데이터 스코프를 강제한다.
> 구현: 인증 미들웨어 + **Eloquent Global Scope**(`HospitalScope`, `SupplierProductScope`)로
> 역할별 자동 필터링. 프론트 필터만으로 처리 금지. 상태 변경은 **Policy**로 이중 검증.

---

## 3. 기술 스택

| 구분 | 기술 | 비고 |
|---|---|---|
| Backend | **Laravel 11** (PHP 8.3) | 단일 앱(모놀리스) |
| Frontend | **Blade + Livewire 3 + Alpine.js** | Vue/React/TypeScript 미사용. PHP 중심 스택 |
| UI | Tailwind CSS | **`DESIGN.md`의 토큰·컴포넌트 사양 강제 준수** |
| 차트 | **Chart.js** (바닐라 JS) | 대시보드 필수. Alpine + Livewire 이벤트로 데이터 갱신 |
| DB | PostgreSQL (MySQL 8 호환 유지) | 트랜잭션 무결성 중요 |
| 인증 | Laravel Breeze (**Blade stack**) + 세션 | 로그인 ID 기반, 세션에 role/org_id |
| 권한 | Gate/Policy + Global Scope | spatie/laravel-permission 미사용(역할 4종 고정이므로 자체 구현) |
| 엑셀 | **maatwebsite/excel** | 업로드 행 단위 검증 리포트 필수 |
| 바코드 | GS1 파서 자체 구현(`app/Support/Gs1Parser.php`) + `html5-qrcode`(카메라, 바닐라 JS) | AI(01)(17)(10)(21) 파싱 |
| 배치 | Laravel Scheduler + Queue(database driver) | 유통기한 경고, 자동 보충, 알림 발송 |
| 테스트 | **Pest** | 파서 단위 테스트 + 핵심 흐름 Feature 테스트 |
| 코드 품질 | Laravel Pint + Larastan (level 6+) | CI에서 강제 |

> JS는 Vite 번들의 **바닐라 ES 모듈**만 사용한다(차트 테마, GS1 스캔 입력, 엑셀 업로드 진행 표시 등).
> 화면 상호작용(모달, 드롭다운, 탭, 토스트)은 Alpine.js, 서버 상태(필터/페이징/승인 등)는 Livewire가 담당한다.

---

## 4. 데이터 모델 (마이그레이션 기준)

### 4.1 설계 원칙 (반드시 준수)
1. **재고 수량은 직접 UPDATE 금지.** 모든 재고 변화는 `stock_transactions`에만 기록하고,
   현재고 캐시 `stock_balances`는 `StockService` 안에서 **같은 DB 트랜잭션으로만** 갱신한다.
2. 모든 입출고/사용은 **Lot 단위** 기록 → Lot 추적성(리콜 대응).
3. 출고는 **FEFO**(유통기한 임박 순) 원칙을 서버 로직으로 강제한다.
4. 전 테이블 `timestamps` + `created_by`. 감사 로그는 `audit_logs` + 모델 Observer로 자동 기록.
5. `monthly_closings`에 마감된 연월의 데이터 생성·수정은 서버단(FormRequest/Service)에서 차단.
6. 상태값은 전부 **PHP 8 Backed Enum**(`app/Enums/`)으로 정의하고 DB는 string 컬럼.

### 4.2 테이블 정의

```php
// organizations — 본사/창고/병원/공급사 통합
id, org_type(enum: HQ|WAREHOUSE|HOSPITAL|SUPPLIER), code(unique), name,
biz_reg_no, hpid_no(요양기관번호), address, tel, is_active, timestamps

// users
id, login_id(unique), password, name, role(enum=OrgType), org_id(FK), is_active, timestamps

// products — 의료 제품 마스터
id, product_code(unique), product_name, udi_di, gtin(index, 바코드 매칭 키),
edi_code(보험코드), spec(규격/모델), manufacturer, supplier_id(FK organizations),
unit(default EA), box_qty(BOX당 EA), purchase_price(decimal 15,2), sales_price(decimal 15,2),
storage_type(enum: ROOM|COLD|FROZEN), is_sterile, use_lot_control, use_expiry, is_active

// product_lots — 제품 × Lot × 유통기한
id, product_id(FK), lot_no, expiry_date(nullable date), unique(product_id, lot_no)

// stock_transactions — 유일한 재고 변경 경로
id, tx_type(enum: IN_SUPPLIER|OUT_TO_HOSPITAL|IN_HOSPITAL|USE|ADJUST|
            RETURN_HOSPITAL|RETURN_SUPPLIER|TRANSFER),
org_id(재고 위치), product_id, lot_id, qty(int, 입고+/출고-), unit_price,
ref_type(INBOUND|OUTBOUND|USAGE|STOCKTAKE...), ref_id, memo, created_by, created_at
// index(org_id, product_id, lot_id), index(ref_type, ref_id)

// stock_balances — 현재고 캐시 (StockService에서만 갱신)
org_id, product_id, lot_id, qty  — PK(org_id, product_id, lot_id)

// safety_stocks — 병원 × 품목
hospital_id, product_id, safety_qty, max_qty, reorder_qty — PK(hospital_id, product_id)

// inbounds / inbound_items — 입고 (공급사→창고, 창고→병원 공용)
inbounds: id, inbound_no(unique IB-YYYYMMDD-####), direction(enum: SUPPLIER_TO_WH|WH_TO_HOSPITAL),
  from_org_id, to_org_id, status(enum: PLANNED|RECEIVING|CONFIRMED|CANCELED),
  planned_date, confirmed_at, outbound_id(nullable, 창고→병원일 때 원 출고 참조)
inbound_items: id, inbound_id, product_id, lot_no, expiry_date, qty, scanned_barcode

// outbounds / outbound_items — 출고 지시 (창고→병원)
outbounds: id, outbound_no(unique), hospital_id, status(enum: DRAFT|APPROVED|PICKING|
  SHIPPED|DELIVERED|CANCELED), source_type(AUTO_REPLENISH|MANUAL), shipped_at, delivered_at
outbound_items: id, outbound_id, product_id, lot_id(FEFO 자동 배정), qty

// usage_reports / usage_report_items — 사용분 보고 (핵심)
usage_reports: id, report_no(unique UR-YYYYMM-HOSP-####), hospital_id,
  status(enum: DRAFT|SUBMITTED|APPROVED|REJECTED), usage_date,
  submitted_at, approved_at, approved_by, reject_reason, total_amount
usage_report_items: id, usage_report_id, product_id, lot_id, qty,
  unit_price, amount, dept(사용부서), procedure_info(시술정보)

// settlements / settlement_items — 월 정산
settlements: id, year_month("2026-07"), org_id, settle_type(enum: SALES|PURCHASE),
  status(enum: OPEN|CONFIRMED|CLOSED), total_qty, total_amount
  unique(year_month, org_id, settle_type)
settlement_items: id, settlement_id, usage_report_item_id, product_id, qty, amount

// monthly_closings
year_month(PK), closed_at, closed_by

// notifications (자체 테이블 — Laravel 기본 notifications와 별도, 화면 요건에 맞춤)
id, noti_type(enum: SAFETY_STOCK|EXPIRY|USAGE_SUBMITTED|USAGE_REJECTED|INBOUND_DELAY|RECALL),
severity(enum: INFO|WARNING|CRITICAL), target_role, target_org_id,
title, message, link_url, is_read, created_at

// stocktakes / stocktake_items — 재고 실사
stocktakes: id, org_id, status(DRAFT|COUNTING|CONFIRMED), confirmed_at
stocktake_items: id, stocktake_id, lot_id, system_qty, counted_qty, diff_qty, reason
// 확정 시 diff만큼 ADJUST 트랜잭션 생성

// audit_logs
id, user_id, action(CREATE|UPDATE|DELETE|APPROVE|REJECT|LOGIN...),
entity, entity_id, before(json), after(json), created_at
```

---

## 5. 바코드 규격 (GS1 파서)

의료기기 UDI 바코드(GS1-128, DataMatrix 2D)는 AI 구조로 구성된다.
스캔 문자열 예: `01(GTIN 14자리)17(유통기한 YYMMDD)10(Lot 가변)`

```
(01) GTIN     — 14자리 고정 → products.gtin 매칭
(17) 유통기한  — 6자리 YYMMDD
(10) Lot 번호  — 가변길이 (FNC1/GS 구분자로 종료)
(21) 시리얼    — 가변길이
```

**구현 요구:**
- `app/Support/Gs1Parser.php`: 스캔 문자열 → `Gs1Data{gtin, expiryDate, lotNo, serial}` DTO 반환.
  괄호 포함/미포함, GS(ASCII 29) 구분자 모두 처리. **Pest 단위 테스트 필수.**
- `POST /api/barcode/parse` 엔드포인트: 파싱 + GTIN으로 제품 조회 → 제품·Lot·유통기한 반환.
- 입고/사용분 화면: 스캔 입력창 포커스 → 스캔 시 자동 세팅(DESIGN.md §4.3 Scan Pulse).
- GTIN 미등록 제품이면 경고 후 제품 매핑 화면으로 유도.
- 모바일: `html5-qrcode`로 카메라 스캔 지원.

---

## 6. 화면(라우트) 구조 — Blade + Livewire

공통 리스트 화면 규격: **① 검색 필터 영역 → ② 테이블(정렬/체크박스) → ③ 페이징(10/30/50/100)**
모든 리스트에 [엑셀 다운로드] 버튼(현재 필터 적용 결과 기준).

```
/login
/dashboard                      # 역할별 다른 대시보드 (Chart.js)

/master/products                # 제품 마스터 (엑셀 업로드)
/master/organizations           # 거래처(병원/공급사/창고)
/master/users                   # 사용자/권한
/master/safety-stocks           # 안전재고 설정 (엑셀 업로드, 자동 산출 추천)

/inventory/status               # 재고 현황 (창고/병원별, Lot·유통기한 단위)
/inventory/expiry               # 유통기한 임박 (D-30/60/90 필터)
/inventory/stocktakes           # 재고 실사
/inventory/lot-trace            # Lot 추적 (리콜 대응)

/inbounds/asn                   # 입고 예정(ASN) — 공급사 등록
/inbounds/receiving             # 입고 검수/확정 — 바코드 스캔
/outbounds                      # 출고 지시 (자동보충 제안 포함)
/outbounds/picking              # 피킹/출고 확정 (FEFO)
/outbounds/delivery             # 배송 현황

/usages/create                  # 사용분 등록 — 스캔/수기/엑셀 (병원)
/usages/approval                # 사용분 승인/반려 (본사, 일괄 승인)
/usages                         # 사용분 이력

/settlements                    # 월 정산 (병원 매출 / 공급사 매입)
/settlements/closing            # 월 마감

/supplier/stocks                # 공급사: 자사 제품 병원별 재고
/supplier/shortages             # 공급사: 부족 품목 / 납품 요청

/notifications                  # 알림 센터
/admin/audit-logs               # 감사 로그
```

라우트는 `routes/web.php`에서 역할별 미들웨어 그룹으로 묶는다:
`role:HQ`, `role:HQ,WAREHOUSE` 형식의 커스텀 미들웨어.

### 역할별 대시보드 (Chart.js)
| 역할 | 차트 |
|---|---|
| 본사 | 월별 사용분 매출 추이(Line), 병원별 매출 비중(Doughnut), 품목 TOP10(Bar), KPI 카드(승인대기/안전재고미달/유통기한임박 금액) |
| 창고 | 금일 입출고(Bar), 배송 상태(Doughnut), 재고 회전율(Line) |
| 병원 | 자기 재고 현황, 월별 사용량 추이(Line), 유통기한 임박 리스트 |
| 공급사 | 병원별 자사 재고(Stacked Bar), 부족 발생 품목, 납품 진행 현황 |

---

## 7. 핵심 비즈니스 로직 규칙

### 7.1 사용분 승인 (가장 중요한 트랜잭션) — `UsageApprovalService`
```php
DB::transaction(function () {
  1. UsageReport 상태 검증(SUBMITTED만 승인 가능, 아니면 409) → APPROVED
  2. 각 item에 대해 StockService::apply(USE, qty 음수)
  3. stock_balances 차감 — 재고 부족(음수) 시 예외 → 전체 롤백 + 한국어 에러
  4. SettlementItem 생성 (SALES: 병원 / PURCHASE: 공급사 쌍으로)
  5. ReplenishmentService::check(병원) → 미달 품목 알림 + 자동보충 제안
});
// 승인 완료 후 Queue로 알림 발송(트랜잭션 밖, afterCommit)
```

### 7.2 자동 보충 (Replenishment) — `ReplenishmentService`
- 트리거: 사용분 승인 후 / 매일 새벽 Scheduler(`replenishment:check`)
- 병원 재고 < 안전재고 →
  - 창고 재고 충분 → `Outbound(DRAFT, AUTO_REPLENISH)` 자동 생성 + 창고 알림
  - 창고 재고 부족 → 공급사 부족 알림 + 발주 요청 리스트 등재

### 7.3 FEFO 출고 — `StockService::allocateFefo()`
- 출고 확정 시 해당 창고 Lot 재고를 `expiry_date ASC NULLS LAST` 정렬로 자동 배정.
- 유통기한 경과 Lot은 후보 제외 + 경고. 동시성 대비 `lockForUpdate()` 사용.

### 7.4 유통기한 경고 배치 — Scheduler 매일 06:00 (`expiry:alert`)
- D-90: INFO / D-60: WARNING / D-30 및 경과: CRITICAL 알림 생성 (병원·창고·본사)

### 7.5 엑셀 업/다운로드 (maatwebsite/excel)
- Export: 전 리스트 공통 — 컨트롤러의 동일 필터 쿼리를 재사용(`?export=1`), FromQuery + chunk.
- Import: `WithHeadingRow` + FormRequest 규칙 재사용 검증 →
  결과 리포트 `{성공 N건, 실패 N건, 실패행: [행번호, 사유]}` 반환,
  **실패 행만 담긴 엑셀 재다운로드** 제공. 템플릿 다운로드 별도 제공.
- 검증 오류 예: 제품코드 미존재, 유통기한 형식 오류, 유통기한 경과, 수량 음수, 마감월 데이터
- 대상: 제품 마스터, 사용분, 안전재고, 입고 예정(ASN)

### 7.6 마감 — `ClosingService`
- `monthly_closings`에 존재하는 연월 대상 생성/수정 요청은 403 + 안내 메시지.
- 검증 위치: FormRequest 공통 rule(`NotClosedMonth`) + Service 이중 차단.
- 마감 취소는 HQ 관리자만, audit_logs 필수 기록.

---

## 8. 백엔드 설계 규칙

- **컨트롤러는 얇게**: 검증(FormRequest) → Service 호출 → Inertia/JSON 응답. 비즈니스 로직 금지.
- **재고 변경은 `app/Services/StockService.php` 단일 진입점.**
  다른 어디서도 `stock_balances`를 쿼리빌더로 직접 수정하지 않는다.
- 리스트 화면은 **Livewire 컴포넌트**(`WithPagination`)로 구현: 필터 속성 `#[Url]`로 쿼리스트링 동기화,
  페이지 크기 10/30/50/100, 필터 로직은 모델 `scopeFilter()` 재사용(Export와 공유).
- 정렬/체크박스 일괄 선택도 Livewire 속성으로 처리, 일괄 승인은 단일 Livewire 액션 → Service 호출.
- 에러: 도메인 예외(`app/Exceptions/DomainException`) → 한국어 메시지로 렌더.
  승인/마감 등 상태 변경은 멱등성 보장(이미 처리된 상태면 409).
- 문서번호 채번(`IB-YYYYMMDD-####` 등)은 `DocumentNoService`에서 시퀀스 테이블 + lock으로 생성.
- N+1 금지: 리스트 쿼리는 `with()` 명시, `Model::preventLazyLoading()` (로컬에서 강제).

---

## 9. 코드 컨벤션 / 폴더 구조

```
app/
  Enums/            # OrgType, TxType, UsageStatus, InboundStatus ... (Backed Enum)
  Models/           # Eloquent (Global Scope 포함)
  Livewire/         # 리스트/폼 화면 컴포넌트 (도메인별 폴더: Master, Inventory,
                    # Inbound, Outbound, Usage, Settlement, Supplier, Dashboard)
  Http/
    Controllers/    # 단순 페이지/다운로드/바코드 파싱 API 등 최소한만
    Requests/       # FormRequest (엑셀 Import·Livewire rules와 규칙 공유)
    Middleware/     # EnsureRole
  Policies/
  Services/         # StockService, UsageApprovalService, ReplenishmentService,
                    # ClosingService, DocumentNoService, LotTraceService
  Support/          # Gs1Parser, Money(₩ 포맷), ExcelFailReport
  Imports/ Exports/ # maatwebsite
  Observers/        # AuditLogObserver
  Console/Commands/ # expiry:alert, replenishment:check
resources/
  views/
    components/     # DESIGN.md 공통 Blade 컴포넌트: <x-flow-rail>, <x-lot-chip>,
                    # <x-status-badge>, <x-stock-level-cell>, <x-kpi-card>,
                    # <x-data-table>, <x-filter-bar>, <x-scan-input>
    layouts/        # 사이드바/상단바 레이아웃 (DESIGN.md §3)
    livewire/       # Livewire 컴포넌트 뷰
  css/
    tokens.css      # DESIGN.md §1 토큰
    app.css
  js/
    app.js          # Alpine + Livewire 부트
    charts/theme.js # Chart.js 공통 테마 (DESIGN.md §5.5)
    scan.js         # 스캔 입력 + html5-qrcode + Scan Pulse
tests/
  Unit/Gs1ParserTest.php
  Feature/          # 사용분 승인, FEFO, 마감 차단, 권한 스코프
  Livewire/         # 필터/페이징/일괄 승인 컴포넌트 테스트
```

- PHP 8.3 문법, strict_types. JS는 바닐라 ES 모듈만(프레임워크·TypeScript 금지), 로직은 최대한 서버(PHP)에 둔다.
- 날짜 서버 UTC 저장 / 화면 KST(`Asia/Seoul`) 표시. 금액 decimal(15,2), 화면 `₩1,234,000`.
- 모든 화면 텍스트·검증 메시지 한국어(`lang/ko`).
- 화면 안 즉석 스타일 금지 — DESIGN.md 공통 컴포넌트만 조립.

---

## 10. 개발 단계 (Phase)

작업 시 아래 순서로 진행하고, 각 Phase 완료 후 `php artisan test` + 빌드 확인 후 다음으로 넘어간다.

- [x] **Phase 1 — 기반**: Laravel 11 + Breeze(Blade) + Livewire 3 + Alpine 셋업, 전체 마이그레이션/Enum/모델,
      시더(조직 4종, 사용자, 제품 30개, Lot/재고), 역할 미들웨어 + Global Scope + Policy
- [x] **Phase 2 — 디자인 시스템 + 공통 컴포넌트**: `DESIGN.md` 토큰(tailwind 매핑,
      charts/theme.js) → 공통 Blade 컴포넌트(x-flow-rail, x-lot-chip, x-status-badge,
      x-stock-level-cell, x-kpi-card, x-page-header, 커스텀 토스트/확인) + 레이아웃(관제탑 셸),
      엑셀 Import/Export 공통 모듈, Gs1Parser(+Pest), 알림 센터(Livewire 폴링)
      ※ 리스트 그리드는 Tabulator(SmartGrid 래퍼)로 구현 — 인라인편집/행추가/일괄삭제
- [x] **Phase 3 — 마스터**: 제품/거래처/사용자/안전재고 CRUD(Tabulator 인라인) + 엑셀 업로드
- [x] **Phase 4 — 재고/입출고**: StockService(단일 진입점·FEFO·lockForUpdate), 재고 현황, ASN→입고 검수(스캔),
      출고 지시→FEFO 피킹→배송, 재고 실사, Lot 추적
- [x] **Phase 5 — 사용분/정산**: 사용분 등록(스캔/재고선택)→전송→승인/반려(UsageApprovalService: 재고 차감+SALES/PURCHASE 정산 생성),
      월 정산, 마감(ClosingService + NotClosedMonth 규칙)
- [x] **Phase 6 — 자동화/대시보드**: 자동 보충(ReplenishmentService·승인 후 연동), 유통기한 배치(expiry:alert/replenishment:check + Scheduler),
      역할별 대시보드(Chart.js 실데이터·월별 추이), 공급사 화면(자사 제품 병원별 재고/부족), 알림 규칙 연결
- [x] **Phase 7 — 마감 품질**: 감사 로그(AuditLogObserver·CRUD 자동기록 + 로그인/마감 CLOSE·REOPEN, 본사 조회 화면),
      권한 스코프 전수 Feature 테스트(EnsureRole 403 + Global Scope HTTP), 모바일 반응형(관제탑 셸 드로어),
      E2E 시나리오 테스트(입고→자동보충→배송→사용분 승인→정산→Lot 추적 관통), Larastan(0)/Pint 통과

---

## 11. 테스트 시나리오 (Pest Feature — 필수 흐름)

1. 공급사 A가 ASN 등록 → 창고 스캔 입고 확정 → 창고 stock_balances 증가 확인
2. 병원 B 안전재고 미달 → 자동보충 Outbound(DRAFT) 생성 → FEFO 피킹 → 병원 입고 확정
3. 병원 B 사용분 등록 → 전송 → 본사 승인 → 병원 재고 차감 + SALES/PURCHASE 정산 항목 생성 확인
4. 승인으로 재차 안전재고 미달 → 알림 + 보충 제안 재생성 확인
5. 재고 초과 사용분 승인 시도 → 롤백 + 재고 불변 + 한국어 에러 확인
6. 마감된 월의 사용분 수정 시도 → 403 확인
7. 특정 Lot 추적 → 창고입고/병원출고/사용 이력 전체 조회 확인
8. HOSPITAL 계정으로 타 병원 데이터 조회 시도 → Global Scope로 0건 / 404 확인
9. SUPPLIER 계정으로 타사 제품 재고 조회 시도 → 0건 확인
