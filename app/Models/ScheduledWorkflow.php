<?php

namespace App\Models;

use Cron\CronExpression;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScheduledWorkflow extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'created_by',
        'workflow_id',
        'target_id',
        'name',
        'description',
        'cron_expression',
        'input_data',
        'is_active',
        'next_run_at',
        'last_run_at',
        'run_count',
        'last_status',
    ];

    protected $casts = [
        'input_data'  => 'array',
        'is_active'   => 'boolean',
        'next_run_at' => 'datetime',
        'last_run_at' => 'datetime',
        'run_count'   => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Target::class);
    }

    public function computeNextRun(): \DateTime
    {
        return (new CronExpression($this->cron_expression))->getNextRunDate();
    }

    public function isDue(): bool
    {
        return $this->is_active && $this->next_run_at !== null && $this->next_run_at->isPast();
    }
}
