<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrgType;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;

/**
 * 로그인 ID 기반 사용자. role + org_id 조합이 모든 데이터 스코프의 기준이다.
 *
 * @property int $id
 * @property string $login_id
 * @property string|null $email
 * @property string $name
 * @property OrgType $role
 * @property int $org_id
 * @property UserStatus $status
 * @property bool $is_active
 * @property Carbon|null $last_login_at
 * @property Carbon|null $approved_at
 * @property-read Organization $organization
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    protected $fillable = [
        'login_id',
        'email',
        'password',
        'name',
        'role',
        'org_id',
        'status',
        'tel',
        'is_active',
        'approved_at',
        'approved_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => OrgType::class,
            'status' => UserStatus::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    public function isHq(): bool
    {
        return $this->role === OrgType::HQ;
    }

    public function isWarehouse(): bool
    {
        return $this->role === OrgType::WAREHOUSE;
    }

    public function isHospital(): bool
    {
        return $this->role === OrgType::HOSPITAL;
    }

    public function isSupplier(): bool
    {
        return $this->role === OrgType::SUPPLIER;
    }

    /** 라이프사이언스(요청) — 병원 대신 물품 요청·사용확정·반납을 수행한다. */
    public function isLife(): bool
    {
        return $this->role === OrgType::LIFE;
    }

    /**
     * 주어진 역할 중 하나인지 확인한다(미들웨어/Policy 공용).
     */
    public function hasRole(OrgType ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
