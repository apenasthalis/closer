<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasUuids;

    protected $fillable = [
        'lead_id', 'contract_id', 'proposal_id', 'kind', 'installment_number',
        'amount', 'due_at', 'paid_at', 'status', 'method', 'enters_cash', 'notes',
        'next_action', 'next_action_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_at' => 'date',
            'paid_at' => 'datetime',
            'next_action_at' => 'datetime',
            'installment_number' => 'integer',
            'enters_cash' => 'boolean',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }
}
