<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkflowRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'graph_data' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'status' => 'nullable|string|in:draft,published',
        ];
    }
}
