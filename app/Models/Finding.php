<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Finding extends Model
{
    use HasUuids;

    protected $fillable = [
        'execution_id',
        'finding_order',
        'title',
        'severity',
        'owasp_category',
        'cwe_id',
        'description',
        'impact',
        'recommendation',
        'poc_steps',
        'request',
        'response',
    ];

    protected function casts(): array
    {
        return [
            'poc_steps' => 'array',
        ];
    }

    public function execution(): BelongsTo
    {
        return $this->belongsTo(Execution::class);
    }
}
