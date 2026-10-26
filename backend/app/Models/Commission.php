<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commission extends Model
{
    use HasUuids;

    protected $fillable = [
        'lead_id', 'user_id', 'rule_id', 'rule_snapshot', 'sale_amount',
        'received_amount', 'percentage', 'fixed_amount', 'amount', 'status',
        'period', 'calculated_at', 'approved_at', 'approved_by', 'paid_at',
        'reversed_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'rule_snapshot' => 'array',
            'sale_amount' => 'decimal:2',
            'received_amount' => 'decimal:2',
            'percentage' => 'decimal:2',
            'fixed_amount' => 'decimal:2',
            'amount' => 'decimal:2',
            'calculated_at' => 'datetime',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(CommissionRule::class);
    }
}
