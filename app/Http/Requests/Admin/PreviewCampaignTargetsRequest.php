<?php

namespace App\Http\Requests\Admin;

use App\Domain\Campaigns\Enums\CampaignTargetType;
use App\Http\Requests\Admin\Concerns\ValidatesCampaignTargets;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewCampaignTargetsRequest extends FormRequest
{
    use ValidatesCampaignTargets;

    public function authorize(): bool
    {
        return $this->user()->hasPermission('campaigns.view');
    }

    public function rules(): array
    {
        return [
            'targets' => ['present', 'array', 'max:200'],
            'targets.*' => ['required', 'array'],
            'targets.*.target_type' => ['required', 'string', Rule::enum(CampaignTargetType::class)],
            'targets.*.target_id' => ['nullable', 'integer'],
            'targets.*.target_value' => ['nullable', 'string', 'max:160'],
            'targets.*.is_exclusion' => ['boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(fn ($validator) => $this->validateTargets($validator));
    }
}
