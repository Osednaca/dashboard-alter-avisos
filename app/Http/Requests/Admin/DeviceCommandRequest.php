<?php

namespace App\Http\Requests\Admin;

use App\Domain\Devices\Enums\DeviceCommandType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeviceCommandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('devices.commands');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'command' => ['required', Rule::enum(DeviceCommandType::class)],
            'payload' => ['nullable', 'array'],
            'confirm' => ['nullable', 'boolean'],
        ];
    }
}
