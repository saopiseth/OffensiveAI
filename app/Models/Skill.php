<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Skill extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'created_by',
        'converted_from',
        'name',
        'description',
        'version',
        'category',
        'is_active',
        'tags',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sourceSkill(): BelongsTo
    {
        return $this->belongsTo(Skill::class, 'converted_from');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(SkillStep::class)->orderBy('execution_order');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(Execution::class);
    }
}
