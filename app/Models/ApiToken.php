<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * 모바일 앱 Bearer 토큰. 평문은 발급 시 1회만 노출되고 DB 에는 sha256 해시만 남는다.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $token_hash
 * @property Carbon|null $expires_at
 * @property-read User $user
 */
class ApiToken extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'token_hash',
        'platform',
        'push_token',
        'last_used_at',
        'expires_at',
    ];

    protected $hidden = ['token_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** 스캔 작업 특성상 세션이 자주 끊기면 안 되므로 기본 유효기간은 30일이다. */
    public const TTL_DAYS = 30;

    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * 새 토큰을 발급하고 평문을 함께 반환한다.
     *
     * @return array{token: ApiToken, plain: string}
     */
    public static function issue(User $user, string $deviceName, ?string $platform = null): array
    {
        $plain = Str::random(64);

        $token = static::create([
            'user_id' => $user->id,
            'name' => mb_substr($deviceName, 0, 100),
            'token_hash' => static::hash($plain),
            'platform' => $platform,
            'last_used_at' => now(),
            'expires_at' => now()->addDays(static::TTL_DAYS),
        ]);

        return ['token' => $token, 'plain' => $plain];
    }

    /**
     * @param  Builder<ApiToken>  $query
     * @return Builder<ApiToken>
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }
}
