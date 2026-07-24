<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 문서번호 채번 시퀀스. DocumentNoService 가 lockForUpdate 로만 접근한다.
 *
 * @property string $prefix
 * @property int $last_no
 */
class DocumentSequence extends Model
{
    protected $primaryKey = 'prefix';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'prefix',
        'last_no',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_no' => 'integer',
        ];
    }
}
