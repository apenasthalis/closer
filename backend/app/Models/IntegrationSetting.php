<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationSetting extends Model
{
    protected $fillable = ['provider', 'enabled', 'status', 'config', 'last_checked_at', 'updated_by'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'config' => 'array',
            'last_checked_at' => 'datetime',
        ];
    }
}
