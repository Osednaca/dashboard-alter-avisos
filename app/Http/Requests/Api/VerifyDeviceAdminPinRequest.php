<?php

namespace App\Http\Requests\Api;

use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Devices\Models\Device;
use Illuminate\Foundation\Http\FormRequest;

class VerifyDeviceAdminPinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof Device && $this->user()->status !== DeviceStatus::Disabled;
    }

    public function rules(): array
    {
        return ['pin' => ['required', 'string', 'regex:/\A[0-9]{6}\z/']];
    }
}
