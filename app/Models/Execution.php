<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Execution extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'skill_id',
        'workflow_id',
        'target_id',
        'scheduled_workflow_id',
        'parent_execution_id',
        'type',
        'run_name',
        'status',
        'input_data',
        'output_data',
        'error_message',
        'retry_count',
        'started_at',
        'completed_at',
        'report_status',
        'report_error',
        'report_generated_at',
    ];

    protected function casts(): array
    {
        return [
            'input_data'          => 'array',
            'output_data'         => 'array',
            'started_at'          => 'datetime',
            'completed_at'        => 'datetime',
            'report_generated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Target::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ExecutionLog::class)->orderBy('step_order');
    }

    public function childExecutions(): HasMany
    {
        return $this->hasMany(Execution::class, 'parent_execution_id')->orderBy('created_at');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class)->orderBy('finding_order');
    }

    public function executionTargets(): HasMany
    {
        return $this->hasMany(ExecutionTarget::class)->orderBy('created_at');
    }

    public function getDurationAttribute(): ?int
    {
        if ($this->started_at && $this->completed_at) {
            return $this->started_at->diffInSeconds($this->completed_at);
        }
        return null;
    }
}
