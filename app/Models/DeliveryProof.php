<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * 배송 증빙 — 기사가 병원에 물건을 넘기며 남긴 사진과 인수 서명.
 *
 * @property int $id
 * @property int $outbound_id
 * @property string|null $signer_name
 * @property string|null $signature_path
 * @property string|null $memo
 * @property Carbon|null $delivered_at
 * @property-read Collection<int, DeliveryPhoto> $photos
 */
class DeliveryProof extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'outbound_id',
        'signer_name',
        'signature_path',
        'memo',
        'delivered_by',
        'delivered_at',
    ];

    /** @var array<string, string> */
    protected $casts = ['delivered_at' => 'datetime'];

    /** @return BelongsTo<Outbound, $this> */
    public function outbound(): BelongsTo
    {
        return $this->belongsTo(Outbound::class);
    }

    /** @return HasMany<DeliveryPhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(DeliveryPhoto::class);
    }

    /** @return BelongsTo<User, $this> */
    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    public function hasSignature(): bool
    {
        return $this->signature_path !== null && $this->signature_path !== '';
    }

    public function signatureUrl(): ?string
    {
        return $this->hasSignature() ? asset('storage/'.$this->signature_path) : null;
    }
}
