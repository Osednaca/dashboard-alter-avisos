<?php

namespace App\Http\Requests\Admin;

use App\Domain\Users\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

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
            'roles.*' => ['string', Rule::exists('roles', 'name')],
            'business_id' => ['nullable', 'integer', 'exists:businesses,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $roles = $this->input('roles', []);

            if (in_array('business-user', $roles, true) && ! $this->filled('business_id')) {
                $validator->errors()->add('business_id', 'Asigna un negocio al usuario de negocio.');
            }
        });
    }
}
