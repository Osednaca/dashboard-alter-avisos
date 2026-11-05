<?php

namespace App\Http\Requests\Admin;

use App\Domain\Advertisers\Enums\AdvertiserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdvertiserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('advertisers.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $advertiserId = $this->route('advertiser')?->id;

        return [
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:180', 'alpha_dash', Rule::unique('advertisers', 'slug')->ignore($advertiserId)],
            'status' => ['required', Rule::enum(AdvertiserStatus::class)],
            'contact_name' => ['nullable', 'string', 'max:160'],
            'contact_email' => ['nullable', 'email', 'max:160'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'billing_name' => ['nullable', 'string', 'max:180'],
            'billing_tax_id' => ['nullable', 'string', 'max:40'],
            'billing_email' => ['nullable', 'email', 'max:160'],
            'billing_address' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug') && $this->filled('name')) {
            $this->merge(['slug' => str($this->string('name'))->slug()->toString()]);
        }
    }
}
