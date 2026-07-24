<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrgType;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 본사/물류창고/거점병원/공급사 통합 조직 마스터.
 *
 * @property int $id
 * @property OrgType $org_type
 * @property string $code
 * @property string $name
 * @property bool $is_active
 */
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    protected $fillable = [
        'org_type',
        'code',
        'name',
        'biz_reg_no',
        'hpid_no',
        'address',
        'tel',
        'is_active',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'org_type' => OrgType::class,
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'org_id');
    }

    /**
     * 공급사인 경우 자사 제품 목록.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'supplier_id');
    }

    /** @return HasMany<SafetyStock, $this> */
    public function safetyStocks(): HasMany
    {
        return $this->hasMany(SafetyStock::class, 'hospital_id');
    }

    /**
     * @param  Builder<Organization>  $query
     * @return Builder<Organization>
     */
    public function scopeOfType(Builder $query, OrgType $type): Builder
    {
        return $query->where('org_type', $type);
    }

    /**
     * @param  Builder<Organization>  $query
     * @return Builder<Organization>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * 리스트 화면 공통 필터. Export 와 동일한 쿼리를 공유한다.
     *
     * @param  Builder<Organization>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Organization>
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['org_type'] ?? null, fn (Builder $q, $v) => $q->where('org_type', $v))
            ->when(isset($filters['is_active']) && $filters['is_active'] !== '',
                fn (Builder $q) => $q->where('is_active', (bool) $filters['is_active']))
            ->when($filters['keyword'] ?? null, function (Builder $q, string $keyword) {
                $q->where(function (Builder $sub) use ($keyword) {
                    $sub->where('name', 'like', "%{$keyword}%")
                        ->orWhere('code', 'like', "%{$keyword}%")
                        ->orWhere('biz_reg_no', 'like', "%{$keyword}%");
                });
            });
    }
}
