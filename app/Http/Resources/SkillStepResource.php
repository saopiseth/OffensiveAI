<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SkillStepResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'skill_id' => $this->skill_id,
            'name' => $this->name,
            'description' => $this->description,
            'prompt_template' => $this->prompt_template,
            'system_prompt' => $this->system_prompt,
            'input_schema' => $this->input_schema,
            'output_schema' => $this->output_schema,
            'execution_order' => $this->execution_order,
            'ai_provider' => $this->ai_provider,
            'model' => $this->model,
            'temperature' => $this->temperature,
            'max_tokens' => $this->max_tokens,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}
