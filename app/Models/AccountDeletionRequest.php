<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * 계정/데이터 삭제 요청.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string $request_type
 * @property string|null $reason
 * @property string $status
 * @property string|null $ip
 * @property string|null $user_agent
 * @property Carbon|null $processed_at
 * @property Carbon|null $created_at
 */
class AccountDeletionRequest extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'name', 'email', 'phone', 'request_type', 'reason',
        'status', 'ip', 'user_agent', 'processed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'processed_at' => 'datetime',
    ];
}
