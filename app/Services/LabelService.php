<?php

declare(strict_types=1);

namespace App\Services;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Carbon;

/**
 * QR/바코드 라벨 생성 — 출고·대량입고 시 LOT·유효기한 라벨.
 * GS1 AI 구조(01 GTIN / 17 유통기한 / 10 Lot)를 QR 로 인코딩한다(Gs1Parser 와 대칭).
 */
class LabelService
{
    /** GS1 데이터 문자열 — (01)GTIN14 (17)YYMMDD (10)LOT. */
    public function gs1(?string $gtin, ?string $expiry, string $lotNo): string
    {
        $s = '';
        if ($gtin !== null && $gtin !== '') {
            $s .= '01'.str_pad(preg_replace('/\D/', '', $gtin) ?? '', 14, '0', STR_PAD_LEFT);
        }
        if ($expiry !== null && $expiry !== '') {
            $s .= '17'.Carbon::parse($expiry)->format('ymd');
        }
        $s .= '10'.$lotNo;

        return $s;
    }

    /** QR 이미지 data URI(PNG). */
    public function qrDataUri(string $data, int $size = 140): string
    {
        $qr = new QrCode(data: $data, size: $size, margin: 2);

        return (new PngWriter)->write($qr)->getDataUri();
    }

    /**
     * 라벨 1건 데이터(QR + 표시 텍스트) 생성.
     *
     * @return array{product_code:string, product_name:string, lot_no:string, expiry:?string, gs1:string, qr:string}
     */
    public function label(string $productCode, string $productName, ?string $gtin, string $lotNo, ?string $expiry): array
    {
        $gs1 = $this->gs1($gtin, $expiry, $lotNo);

        return [
            'product_code' => $productCode,
            'product_name' => $productName,
            'lot_no' => $lotNo,
            'expiry' => $expiry,
            'gs1' => $gs1,
            'qr' => $this->qrDataUri($gs1),
        ];
    }
}
