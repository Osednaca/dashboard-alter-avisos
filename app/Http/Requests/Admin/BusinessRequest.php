<?php

namespace App\Http\Requests\Admin;

use App\Domain\Businesses\Enums\BusinessCategory;
use App\Domain\Businesses\Enums\BusinessStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BusinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission(
            $this->route('business') ? 'businesses.edit' : 'businesses.create'
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $businessId = $this->route('business')?->id;

        return [
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:180', 'alpha_dash', Rule::unique('businesses', 'slug')->ignore($businessId)],
            'category' => ['required', Rule::enum(BusinessCategory::class)],
            'status' => ['required', Rule::enum(BusinessStatus::class)],
            'timezone' => ['required', 'string', 'timezone'],
            'contact_name' => ['nullable', 'string', 'max:160'],
            'contact_email' => ['nullable', 'email', 'max:160'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug') && $this->filled('name')) {
            $this->merge(['slug' => str($this->string('name'))->slug()->toString()]);
        }
    }
}
