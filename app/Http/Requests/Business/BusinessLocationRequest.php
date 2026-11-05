<?php

namespace App\Http\Requests\Business;

use App\Domain\Locations\Enums\LocationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BusinessLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('business.settings.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'country' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'timezone' => ['required', 'string', 'timezone'],
            'status' => ['required', Rule::enum(LocationStatus::class)],
        ];
    }
}
