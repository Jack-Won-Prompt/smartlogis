<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Scopes\HospitalScope;
use Database\Factories\SafetyStockFactory;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 병원 × 품목 안전재고 기준. 복합 PK(hospital_id, product_id).
 *
 * @property int $hospital_id
 * @property int $product_id
 * @property int $safety_qty
 * @property int $max_qty
 * @property int $reorder_qty
 */
#[ScopedBy(HospitalScope::class)]
class SafetyStock extends Model
{
    /** @use HasFactory<SafetyStockFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $primaryKey = null;

    protected $keyType = 'string';

    protected $fillable = [
        'hospital_id',
        'product_id',
        'safety_qty',
        'max_qty',
        'reorder_qty',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'safety_qty' => 'integer',
            'max_qty' => 'integer',
            'reorder_qty' => 'integer',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'hospital_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @param  Builder<SafetyStock>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<SafetyStock>
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['hospital_id'] ?? null, fn (Builder $q, $v) => $q->where('hospital_id', $v))
            ->when($filters['product_id'] ?? null, fn (Builder $q, $v) => $q->where('product_id', $v));
    }
}
