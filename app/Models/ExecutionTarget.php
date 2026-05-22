<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExecutionTarget extends Model
{
    use HasUuids;

    protected $fillable = [
        'execution_id',
        'target_type',
        'target_value',
        'status',
        'error_message',
        'child_execution_id',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at'   => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function execution(): BelongsTo
    {
        return $this->belongsTo(Execution::class);
    }

    public function childExecution(): BelongsTo
    {
        return $this->belongsTo(Execution::class, 'child_execution_id');
    }
}
