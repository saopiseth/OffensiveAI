<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Target extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'created_by',
        'name',
        'type',
        'value',
        'description',
        'tags',
        'is_active',
    ];

    protected $casts = [
        'tags'      => 'array',
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(Execution::class);
    }

    public function scheduledWorkflows(): HasMany
    {
        return $this->hasMany(ScheduledWorkflow::class);
    }
}
