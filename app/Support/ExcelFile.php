<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * 엑셀 다운로드 파일명 규칙 — "화면명-날짜.xlsx" (예: 거래처-20260724.xlsx).
 */
final class ExcelFile
{
    public static function name(string $screen, string $ext = 'xlsx'): string
    {
        return $screen.'-'.Carbon::now()->timezone('Asia/Seoul')->format('Ymd').'.'.$ext;
    }

    /** 업로드 양식 파일명 — "화면명-양식.xlsx". */
    public static function template(string $screen, string $ext = 'xlsx'): string
    {
        return $screen.'-양식.'.$ext;
    }

    /** 실패 행 파일명 — "화면명-실패행.xlsx". */
    public static function failures(string $screen, string $ext = 'xlsx'): string
    {
        return $screen.'-실패행.'.$ext;
    }
}
