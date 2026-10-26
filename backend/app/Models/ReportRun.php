<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ReportRun extends Model
{
    use HasUuids;

    protected $fillable = [
        'type', 'period_start', 'period_end', 'status', 'metrics',
        'recipients', 'error', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'metrics' => 'array',
            'recipients' => 'array',
        ];
    }
}
