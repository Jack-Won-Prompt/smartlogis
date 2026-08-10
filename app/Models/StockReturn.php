<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReturnStatus;
use App\Models\Scopes\HospitalScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * 반납(병원 → 창고). 병원은 HospitalScope 로 자기 반납만 조회한다.
 *
 * @property int $id
 * @property string $return_no
 * @property int $hospital_id
 * @property int $warehouse_id
 * @property ReturnStatus $status
 * @property string|null $reason
 * @property Carbon|null $received_at
 */
#[ScopedBy(HospitalScope::class)]
class StockReturn extends Model
{
    protected $fillable = [
        'return_no', 'hospital_id', 'warehouse_id', 'status',
        'reason', 'shipped_at', 'received_at', 'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ReturnStatus::class,
            'shipped_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    /** @return HasMany<StockReturnItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(StockReturnItem::class);
    }

    /** @return BelongsTo<Organization, $this> */
    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'hospital_id');
    }

    /** @return BelongsTo<Organization, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'warehouse_id');
    }
}
