<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * GS1-128 / DataMatrix(2D) UDI 바코드 파서.
 *
 * 지원 AI:
 *   (01) GTIN     — 14자리 고정
 *   (17) 유통기한  — 6자리 YYMMDD (DD=00 이면 해당 월 말일)
 *   (10) Lot 번호  — 가변(FNC1/GS(ASCII 29) 또는 다음 AI로 종료)
 *   (21) 시리얼    — 가변
 *
 * 입력 형태 모두 처리:
 *   - 괄호 포함:   (01)08806014100041(17)270331(10)A23K01
 *   - 괄호 미포함: 010880601410004117270331 10A23K01<GS>21SN1
 *   - GS(그룹 구분자, ASCII 29 / \x1d) 로 가변길이 AI 종료
 */
class Gs1Parser
{
    private const GS = "\x1d";

    /** 가변길이 AI 최대 허용 길이(GS1 표준). */
    private const VAR_MAX = 20;

    /** 고정길이 AI 와 그 길이. */
    private const FIXED = ['01' => 14, '17' => 6, '11' => 6, '15' => 6];

    public function parse(string $scan): Gs1Data
    {
        $raw = $scan;
        $scan = trim($scan);

        $ai = str_contains($scan, '(')
            ? $this->parseParenthesised($scan)
            : $this->parseStream($scan);

        $expiryRaw = $this->value($ai, '17');

        return new Gs1Data(
            gtin: $this->value($ai, '01'),
            expiryDate: $expiryRaw !== null ? $this->parseExpiry($expiryRaw) : null,
            lotNo: $this->value($ai, '10'),
            serial: $this->value($ai, '21'),
            raw: $raw,
        );
    }

    /**
     * @param  array<string, string>  $ai
     */
    private function value(array $ai, string $key): ?string
    {
        $v = $ai[$key] ?? null;

        return ($v === null || $v === '') ? null : $v;
    }

    /**
     * 괄호 표기: (AI)value(AI)value ...
     *
     * @return array<string, string>
     */
    private function parseParenthesised(string $scan): array
    {
        $out = [];
        if (preg_match_all('/\((\d{2,4})\)([^(]*)/', $scan, $m, PREG_SET_ORDER)) {
            foreach ($m as $set) {
                $out[$set[1]] = trim($set[2], self::GS.' ');
            }
        }

        return $out;
    }

    /**
     * 괄호 없는 연속 스트림. 고정길이 AI 는 길이로, 가변길이는 GS/다음 AI 로 종료.
     *
     * @return array<string, string>
     */
    private function parseStream(string $scan): array
    {
        // 선행 FNC1(있으면) 제거
        $scan = ltrim($scan, self::GS);
        $out = [];
        $i = 0;
        $len = strlen($scan);

        while ($i < $len) {
            // GS 구분자 스킵
            if ($scan[$i] === self::GS) {
                $i++;

                continue;
            }

            $ai = substr($scan, $i, 2);
            if (! ctype_digit($ai)) {
                break; // 해석 불가 — 중단
            }
            $i += 2;

            if (isset(self::FIXED[$ai])) {
                $value = substr($scan, $i, self::FIXED[$ai]);
                $i += self::FIXED[$ai];
                $out[$ai] = $value;

                continue;
            }

            // 가변길이: GS 또는 문자열 끝까지
            $gsPos = strpos($scan, self::GS, $i);
            $end = $gsPos === false ? $len : $gsPos;
            $value = substr($scan, $i, min($end - $i, self::VAR_MAX));
            $out[$ai] = $value;
            $i = ($gsPos === false) ? $len : $gsPos + 1;
        }

        return $out;
    }

    /**
     * YYMMDD → Carbon. DD=00 이면 해당 월 말일. YY: 00~49→20xx, 50~99→19xx.
     */
    private function parseExpiry(string $yymmdd): ?Carbon
    {
        if (! preg_match('/^\d{6}$/', $yymmdd)) {
            return null;
        }

        $yy = (int) substr($yymmdd, 0, 2);
        $mm = (int) substr($yymmdd, 2, 2);
        $dd = (int) substr($yymmdd, 4, 2);

        if ($mm < 1 || $mm > 12) {
            return null;
        }

        $year = $yy <= 49 ? 2000 + $yy : 1900 + $yy;

        if ($dd === 0) {
            // 해당 월 말일
            return Carbon::create($year, $mm, 1)?->endOfMonth()->startOfDay();
        }

        if ($dd < 1 || $dd > 31) {
            return null;
        }

        return Carbon::create($year, $mm, $dd)?->startOfDay();
    }
}
