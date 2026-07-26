<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * 접속/페이지 접근 로그.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $method
 * @property string $path
 * @property string|null $route
 * @property string|null $ip
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 * @property-read User|null $user
 */
class AccessLog extends Model
{
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['user_id', 'method', 'path', 'route', 'ip', 'user_agent', 'created_at'];

    /** @var array<string, string> */
    protected $casts = ['created_at' => 'datetime'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
