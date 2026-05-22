<?php

namespace App\Http\Requests\Skill;

use Illuminate\Foundation\Http\FormRequest;

class StoreSkillRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'version' => 'nullable|string|max:20',
            'category' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
            'tags' => 'nullable|array',
        ];
    }
}
