<?php

namespace App\Http\Requests\Admin;

use App\Domain\Locations\Enums\LocationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('locations.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['state' => $this->input('state') ?? '']);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('cities')->where(fn ($q) => $q
                ->where('state', $this->input('state'))->where('country', $this->input('country')))
                ->ignore($this->route('location')?->id)],
            'state' => ['nullable', 'string', 'max:120'],
            'country' => ['required', 'string', 'max:120'],
            'timezone' => ['required', 'string', 'timezone'],
            'status' => ['required', Rule::enum(LocationStatus::class)],
            'business_ids' => ['required', 'array', 'min:1'],
            'business_ids.*' => ['required', 'integer', 'distinct', 'exists:businesses,id'],
        ];
    }

    public function messages(): array
    {
        return ['name.unique' => 'Esta ciudad ya existe o fue eliminada. Usa otra combinación de ciudad, departamento y país.'];
    }
}
