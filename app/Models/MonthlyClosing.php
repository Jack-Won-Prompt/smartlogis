<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * 마감된 연월. 존재 자체가 "그 달은 수정 불가"라는 뜻이다.
 *
 * @property string $year_month
 * @property Carbon $closed_at
 */
class MonthlyClosing extends Model
{
    protected $primaryKey = 'year_month';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'year_month',
        'closed_at',
        'closed_by',
        'memo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * 해당 연월("2026-07")이 마감되었는지. FormRequest 규칙과 Service 가 함께 쓴다.
     */
    public static function isClosed(string $yearMonth): bool
    {
        return static::query()->whereKey($yearMonth)->exists();
    }
}
