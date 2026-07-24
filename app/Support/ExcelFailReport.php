<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 엑셀 업로드 검증 결과 리포트 (CLAUDE.md §7.5).
 * {성공 N건, 실패 N건, 실패행:[행번호, 사유]} 를 담고, 실패 행만 재다운로드할 수 있게 한다.
 */
class ExcelFailReport
{
    /** @var array<int, array{row: int, reason: string, data: array<string, mixed>}> */
    private array $failures = [];

    private int $successCount = 0;

    public function addSuccess(): void
    {
        $this->successCount++;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addFailure(int $row, string $reason, array $data = []): void
    {
        $this->failures[] = ['row' => $row, 'reason' => $reason, 'data' => $data];
    }

    public function successCount(): int
    {
        return $this->successCount;
    }

    public function failureCount(): int
    {
        return count($this->failures);
    }

    public function hasFailures(): bool
    {
        return $this->failures !== [];
    }

    /**
     * @return array<int, array{row: int, reason: string, data: array<string, mixed>}>
     */
    public function failures(): array
    {
        return $this->failures;
    }

    /**
     * 화면/JSON 응답용 요약.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->successCount,
            'failed' => $this->failureCount(),
            'failures' => array_map(
                static fn (array $f): array => ['row' => $f['row'], 'reason' => $f['reason']],
                $this->failures
            ),
        ];
    }

    public function summaryMessage(): string
    {
        return "성공 {$this->successCount}건, 실패 {$this->failureCount()}건";
    }
}
