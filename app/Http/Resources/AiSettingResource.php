<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'api_key_masked' => $this->masked_api_key,
            'default_model' => $this->default_model,
            'temperature' => $this->temperature,
            'max_tokens' => $this->max_tokens,
            'is_active' => $this->is_active,
            'extra_config' => $this->extra_config,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
