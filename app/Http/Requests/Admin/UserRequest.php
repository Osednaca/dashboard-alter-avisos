<?php

namespace App\Http\Requests\Admin;

use App\Domain\Users\Enums\RoleEnum;
use App\Domain\Users\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('users.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')->ignore($userId)],
            'job_title' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::enum(UserStatus::class)],
            'password' => [$userId ? 'nullable' : 'required', 'confirmed', Password::min(10)->letters()->numbers()],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'name'), Rule::notIn([RoleEnum::BusinessUser->value])],
        ];
    }
}
