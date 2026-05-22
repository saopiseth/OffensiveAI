<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SkillStep extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'skill_id',
        'name',
        'description',
        'prompt_template',
        'system_prompt',
        'input_schema',
        'output_schema',
        'execution_order',
        'ai_provider',
        'model',
        'temperature',
        'max_tokens',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'input_schema' => 'array',
            'output_schema' => 'array',
            'temperature' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    public function executionLogs(): HasMany
    {
        return $this->hasMany(ExecutionLog::class);
    }

    public function renderPrompt(array $variables): string
    {
        $template = $this->prompt_template;
        foreach ($variables as $key => $value) {
            $scalar = is_array($value) ? json_encode($value) : (string) $value;
            $template = str_replace('{{' . $key . '}}', $scalar, $template);
        }
        return $template;
    }
}
