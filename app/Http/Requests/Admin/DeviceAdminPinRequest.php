<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class DeviceAdminPinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('devices.manage');
    }

    public function rules(): array
    {
        return ['pin' => ['required', 'string', 'regex:/\A[0-9]{6}\z/', 'confirmed']];
    }
}
