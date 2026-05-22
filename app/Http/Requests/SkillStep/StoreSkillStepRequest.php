<?php

namespace App\Http\Requests\SkillStep;

use Illuminate\Foundation\Http\FormRequest;

class StoreSkillStepRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'prompt_template' => 'required|string',
            'system_prompt' => 'nullable|string',
            'input_schema' => 'nullable|array',
            'output_schema' => 'nullable|array',
            'ai_provider' => 'nullable|string|in:claude,openai',
            'model' => 'nullable|string',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'max_tokens' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
        ];
    }
}
