<?php

namespace App\Http\Requests\Admin;

use App\Domain\Locations\Enums\LocationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('locations.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'business_id' => ['required', 'integer', 'exists:businesses,id'],
            'name' => ['required', 'string', 'max:160'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'country' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'timezone' => ['required', 'string', 'timezone'],
            'status' => ['required', Rule::enum(LocationStatus::class)],
        ];
    }
}
