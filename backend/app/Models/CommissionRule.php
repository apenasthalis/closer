<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionRule extends Model
{
    use HasUuids;

    protected $fillable = [
        'name', 'scope', 'user_id', 'team_id', 'niche_id', 'percentage',
        'fixed_amount', 'basis', 'active', 'priority', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
            'fixed_amount' => 'decimal:2',
            'active' => 'boolean',
            'priority' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function niche(): BelongsTo
    {
        return $this->belongsTo(Niche::class);
    }
}
