<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExecutionLog extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'execution_id',
        'skill_step_id',
        'step_order',
        'step_name',
        'status',
        'prompt_rendered',
        'input_data',
        'output_data',
        'error_message',
        'ai_provider',
        'model_used',
        'tokens_used',
        'duration_ms',
    ];

    public function execution(): BelongsTo
    {
        return $this->belongsTo(Execution::class);
    }

    public function skillStep(): BelongsTo
    {
        return $this->belongsTo(SkillStep::class);
    }
}
