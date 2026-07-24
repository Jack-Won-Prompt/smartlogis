<?php

declare(strict_types=1);

use App\Support\Gs1Parser;

beforeEach(function () {
    $this->parser = new Gs1Parser;
});

it('괄호 포함 문자열을 파싱한다', function () {
    $data = $this->parser->parse('(01)08806014100041(17)270331(10)A23K01');

    expect($data->gtin)->toBe('08806014100041');
    expect($data->expiryDate?->toDateString())->toBe('2027-03-31');
    expect($data->lotNo)->toBe('A23K01');
    expect($data->serial)->toBeNull();
});

it('괄호 없는 연속 스트림을 파싱한다 (고정 AI 뒤 가변 Lot)', function () {
    // 01(14) 17(6) 10 가변
    $data = $this->parser->parse('010880601410004117270331'.'10A23K01');

    expect($data->gtin)->toBe('08806014100041');
    expect($data->expiryDate?->toDateString())->toBe('2027-03-31');
    expect($data->lotNo)->toBe('A23K01');
});

it('GS(ASCII 29) 구분자로 가변 AI 종료를 처리한다', function () {
    $gs = "\x1d";
    $scan = '010880601410004117270331'.'10A23K01'.$gs.'21SN12345';
    $data = $this->parser->parse($scan);

    expect($data->gtin)->toBe('08806014100041');
    expect($data->lotNo)->toBe('A23K01');
    expect($data->serial)->toBe('SN12345');
});

it('유통기한 DD=00 은 해당 월 말일로 변환한다', function () {
    $data = $this->parser->parse('(17)270200'); // 2027-02, 말일

    expect($data->expiryDate?->toDateString())->toBe('2027-02-28');
});

it('YY 50 이상은 1900년대로 해석한다', function () {
    $data = $this->parser->parse('(17)991231');

    expect($data->expiryDate?->toDateString())->toBe('1999-12-31');
});

it('GTIN 만 있는 스캔도 처리한다', function () {
    $data = $this->parser->parse('(01)08806014100041');

    expect($data->hasGtin())->toBeTrue();
    expect($data->gtin)->toBe('08806014100041');
    expect($data->expiryDate)->toBeNull();
    expect($data->lotNo)->toBeNull();
});

it('선행 FNC1/GS 가 있어도 파싱한다', function () {
    $gs = "\x1d";
    $data = $this->parser->parse($gs.'010880601410004110LOTX');

    expect($data->gtin)->toBe('08806014100041');
    expect($data->lotNo)->toBe('LOTX');
});

it('잘못된 월은 유통기한을 null 로 둔다', function () {
    $data = $this->parser->parse('(17)271331'); // 13월

    expect($data->expiryDate)->toBeNull();
});

it('toArray 는 날짜를 문자열로 직렬화한다', function () {
    $data = $this->parser->parse('(01)08806014100041(17)270331(10)A23K01');

    expect($data->toArray())->toMatchArray([
        'gtin' => '08806014100041',
        'expiry_date' => '2027-03-31',
        'lot_no' => 'A23K01',
        'serial' => null,
    ]);
});
