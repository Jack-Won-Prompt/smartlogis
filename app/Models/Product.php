<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StorageType;
use App\Models\Scopes\SupplierProductScope;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 의료 제품 마스터. SUPPLIER 계정은 자사 제품만 조회 가능(Global Scope).
 *
 * @property int $id
 * @property string $product_code
 * @property string $product_name
 * @property string|null $gtin
 * @property int $supplier_id
 * @property StorageType $storage_type
 * @property string $sales_price
 * @property string $purchase_price
 * @property bool $is_active
 * @property-read Organization $supplier
 */
#[ScopedBy(SupplierProductScope::class)]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'product_code',
        'product_name',
        'udi_di',
        'gtin',
        'edi_code',
        'spec',
        'manufacturer',
        'supplier_id',
        'unit',
        'box_qty',
        'purchase_price',
        'sales_price',
        'storage_type',
        'is_sterile',
        'use_lot_control',
        'use_expiry',
        'is_active',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'storage_type' => StorageType::class,
            'box_qty' => 'integer',
            'purchase_price' => 'decimal:2',
            'sales_price' => 'decimal:2',
            'is_sterile' => 'boolean',
            'use_lot_control' => 'boolean',
            'use_expiry' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'supplier_id');
    }

    /** @return HasMany<ProductLot, $this> */
    public function lots(): HasMany
    {
        return $this->hasMany(ProductLot::class);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<Product>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Product>
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['supplier_id'] ?? null, fn (Builder $q, $v) => $q->where('supplier_id', $v))
            ->when($filters['storage_type'] ?? null, fn (Builder $q, $v) => $q->where('storage_type', $v))
            ->when(isset($filters['is_active']) && $filters['is_active'] !== '',
                fn (Builder $q) => $q->where('is_active', (bool) $filters['is_active']))
            ->when($filters['keyword'] ?? null, function (Builder $q, string $keyword) {
                $q->where(function (Builder $sub) use ($keyword) {
                    $sub->where('product_name', 'like', "%{$keyword}%")
                        ->orWhere('product_code', 'like', "%{$keyword}%")
                        ->orWhere('gtin', 'like', "%{$keyword}%")
                        ->orWhere('edi_code', 'like', "%{$keyword}%");
                });
            });
    }
}
