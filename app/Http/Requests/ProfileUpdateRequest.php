<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 프로필 수정. 로그인 ID/역할/소속은 본인이 바꿀 수 없고 이름/연락처만 수정한다.
 */
class ProfileUpdateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'tel' => ['nullable', 'string', 'max:30'],
        ];
    }
}
