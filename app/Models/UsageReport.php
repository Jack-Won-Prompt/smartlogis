<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SalesChannel;
use App\Enums\UsageStatus;
use App\Models\Scopes\HospitalScope;
use Database\Factories\UsageReportFactory;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * 사용분 보고. 본사 승인 시 재고 차감 + 정산 항목 생성의 기점이 된다.
 *
 * @property int $id
 * @property string $report_no
 * @property int $hospital_id
 * @property UsageStatus $status
 * @property Carbon $usage_date
 * @property string $total_amount
 */
#[ScopedBy(HospitalScope::class)]
class UsageReport extends Model
{
    /** @use HasFactory<UsageReportFactory> */
    use HasFactory;

    protected $fillable = [
        'report_no',
        'hospital_id',
        'status',
        'usage_date',
        'sales_channel',
        'submitted_at',
        'approved_at',
        'approved_by',
        'reject_reason',
        'total_amount',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => UsageStatus::class,
            'usage_date' => 'date',
            'sales_channel' => SalesChannel::class,
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'total_amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'hospital_id');
    }

    /** @return HasMany<UsageReportItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(UsageReportItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** 사용분이 귀속되는 정산 연월("2026-07"). 마감 검증의 기준. */
    public function yearMonth(): string
    {
        return $this->usage_date->format('Y-m');
    }

    /**
     * @param  Builder<UsageReport>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<UsageReport>
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['status'] ?? null, fn (Builder $q, $v) => $q->where('status', $v))
            ->when($filters['hospital_id'] ?? null, fn (Builder $q, $v) => $q->where('hospital_id', $v))
            ->when($filters['date_from'] ?? null, fn (Builder $q, $v) => $q->whereDate('usage_date', '>=', $v))
            ->when($filters['date_to'] ?? null, fn (Builder $q, $v) => $q->whereDate('usage_date', '<=', $v))
            ->when($filters['keyword'] ?? null, fn (Builder $q, $v) => $q->where('report_no', 'like', "%{$v}%"));
    }
}
