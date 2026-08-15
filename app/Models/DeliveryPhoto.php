<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 배송 증빙 사진 한 장(현장 인수 사진).
 *
 * @property int $id
 * @property int $delivery_proof_id
 * @property string $file_path
 * @property string|null $file_name
 * @property int|null $file_size
 */
class DeliveryPhoto extends Model
{
    /** @var list<string> */
    protected $fillable = ['delivery_proof_id', 'file_path', 'file_name', 'file_size'];

    /** @return BelongsTo<DeliveryProof, $this> */
    public function proof(): BelongsTo
    {
        return $this->belongsTo(DeliveryProof::class, 'delivery_proof_id');
    }

    public function url(): string
    {
        return asset('storage/'.$this->file_path);
    }
}
