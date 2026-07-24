<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * 도메인 규칙 위반 예외. 한국어 메시지로 렌더한다(CLAUDE.md §8).
 * 상태 변경 충돌(이미 처리됨 등)은 409, 그 외 업무 규칙 위반은 422.
 */
class DomainException extends RuntimeException
{
    public function __construct(string $message, private readonly int $status = 422)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }

    /** 이미 처리된 상태 등 충돌(멱등성). */
    public static function conflict(string $message): self
    {
        return new self($message, 409);
    }

    public function render(Request $request): ?JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], $this->status);
        }

        return null; // 웹 요청은 기본 예외 핸들러(세션 에러)로 처리
    }
}
