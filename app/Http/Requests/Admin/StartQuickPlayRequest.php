<?php

namespace App\Http\Requests\Admin;

use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\QuickPlay\Enums\QuickPlayDisplayMode;
use App\Domain\QuickPlay\Enums\QuickPlayScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StartQuickPlayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('quick_play.send');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'media_asset_id' => ['required', 'integer', Rule::exists('media_assets', 'id')->where(fn ($q) => $q->whereIn('id', MediaAsset::query()->advertising()->ready()->select('id')))],
            'display_mode' => ['required', Rule::enum(QuickPlayDisplayMode::class)],
            'scope' => ['required', Rule::enum(QuickPlayScope::class)],
            'duration' => ['nullable', 'integer', 'between:3,3600'],

            'device_ids' => ['nullable', 'array', 'max:500'],
            'device_ids.*' => ['integer', 'exists:devices,id'],
            'business_ids' => ['nullable', 'array', 'max:200'],
            'business_ids.*' => ['integer', 'exists:businesses,id'],
            'location_ids' => ['nullable', 'array', 'max:500'],
            'location_ids.*' => ['integer', 'exists:locations,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $scopeValue = $this->input('scope');
            $scope = is_string($scopeValue) ? QuickPlayScope::tryFrom($scopeValue) : null;

            $required = match ($scope) {
                QuickPlayScope::Devices => 'device_ids',
                QuickPlayScope::Businesses => 'business_ids',
                QuickPlayScope::Locations => 'location_ids',
                default => null,
            };

            if ($required && empty($this->input($required))) {
                $validator->errors()->add('targets', 'Selecciona al menos un objetivo.');
            }

            $media = MediaAsset::query()->find($this->integer('media_asset_id'));

            if ($media && $media->type === MediaType::Image && ! $this->filled('duration')) {
                $validator->errors()->add('duration', 'Indica cuántos segundos debe mostrarse la imagen.');
            }
        });
    }
}
