<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiSetting extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'provider',
        'api_key',
        'default_model',
        'temperature',
        'max_tokens',
        'is_active',
        'extra_config',
    ];

    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return [
            'temperature' => 'float',
            'is_active' => 'boolean',
            'extra_config' => 'array',
        ];
    }

    public function getMaskedApiKeyAttribute(): string
    {
        return substr($this->api_key, 0, 8) . str_repeat('*', max(0, strlen($this->api_key) - 8));
    }
}
