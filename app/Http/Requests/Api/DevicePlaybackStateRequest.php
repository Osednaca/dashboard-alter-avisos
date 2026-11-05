<?php

namespace App\Http\Requests\Api;

use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Devices\Models\Device;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DevicePlaybackStateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof Device && $this->user()->status !== DeviceStatus::Disabled;
    }

    public function rules(): array
    {
        $rules = [
            'schema_version' => ['required', 'integer', 'in:1'],
            'session_id' => ['required', 'uuid'],
            'sequence' => ['required', 'integer', 'between:0,9007199254740991'],
            'sample_age_ms' => ['required', 'integer', 'between:0,15000'],
            'scene' => ['required', 'in:playback,settings,pin,background,activation'],
            'layout' => ['present', 'nullable', 'array:manifest_version,rotation,split,business_percentage,business_first,width_px,height_px', 'min:1'],
            'layout.manifest_version' => ['nullable', 'string', 'regex:/\A[0-9]{1,18}\z/'],
            'layout.rotation' => ['required_with:layout', 'integer', 'in:0,90,180,270'],
            'layout.split' => ['required_with:layout', 'in:side_by_side,top_bottom'],
            'layout.business_percentage' => ['required_with:layout', 'integer', 'between:1,99'],
            'layout.business_first' => ['required_with:layout', 'boolean'],
            'layout.width_px' => ['required_with:layout', 'integer', 'between:1,16384'],
            'layout.height_px' => ['required_with:layout', 'integer', 'between:1,16384'],
            'zones' => ['present', 'array:business,advertising,fullscreen'],
        ];
        foreach (['business', 'advertising', 'fullscreen'] as $zone) {
            $key = 'zones.'.$zone;
            $rules[$key] = ['sometimes', 'required', 'array:source,manifest_version,item_id,media_asset_id,quick_play_device_id,command_id,state,position_ms,duration_ms,live_state,live_creative_id'];
            $rules[$key.'.source'] = ['required_with:'.$key, 'in:manifest,quick_play,live,live_fallback,empty'];
            $rules[$key.'.state'] = ['required_with:'.$key, 'in:playing,paused,buffering,error,empty'];
            $rules[$key.'.manifest_version'] = ['nullable', 'string', 'regex:/\A[0-9]{1,18}\z/'];
            $rules[$key.'.item_id'] = ['nullable', 'string', 'max:100', 'regex:/\A[a-zA-Z0-9_-]+\z/'];
            foreach (['media_asset_id', 'quick_play_device_id', 'command_id', 'live_creative_id'] as $identity) {
                $rules[$key.'.'.$identity] = ['nullable', 'integer', 'between:1,9007199254740991'];
            }
            foreach (['position_ms', 'duration_ms'] as $time) {
                $rules[$key.'.'.$time] = ['nullable', 'integer', 'between:0,86400000'];
            }
            $rules[$key.'.live_state'] = ['nullable', 'in:scheduled,connecting,live,buffering,offline,failed,ended,unverified'];
        }

        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $allowed = ['schema_version', 'session_id', 'sequence', 'sample_age_ms', 'scene', 'layout', 'zones'];
            if (array_diff(array_keys($this->all()), $allowed) || strlen($this->getContent()) > 32768) {
                $validator->errors()->add('playback_state', 'El reporte contiene campos desconocidos o supera el tamaño permitido.');
            }
            if ($this->input('scene') !== 'playback' && $this->input('zones')) {
                $validator->errors()->add('zones', 'Esta escena no puede mostrar zonas de reproducción.');
            }
            if ($this->input('zones.fullscreen') && count((array) $this->input('zones')) !== 1) {
                $validator->errors()->add('zones', 'La pantalla completa reemplaza las demás zonas.');
            }
        }];
    }
}
