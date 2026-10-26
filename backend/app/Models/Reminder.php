<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reminder extends Model
{
    use HasUuids, SoftDeletes;

    public const STATUS_PENDING = 'pendente';

    public const STATUS_DONE = 'concluido';

    public const STATUS_CANCELLED = 'cancelado';

    public const TYPES = [
        'follow_up', 'proposta', 'negociacao', 'cobranca', 'parcela',
        'confirmacao_reuniao', 'remarcacao', 'no_show', 'contrato',
        'tarefa', 'ia', 'outro',
    ];

    protected $fillable = [
        'lead_id', 'type', 'title', 'detail', 'due_at', 'status', 'sent_to_lead',
        'priority', 'snoozed_from', 'origin', 'rule_key', 'dedupe_key', 'rule_meta',
        'rescheduled_count', 'last_rule_run_at', 'responsible_id', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'snoozed_from' => 'datetime',
            'completed_at' => 'datetime',
            'last_rule_run_at' => 'datetime',
            'sent_to_lead' => 'boolean',
            'priority' => 'integer',
            'rescheduled_count' => 'integer',
            'rule_meta' => 'array',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->due_at->isPast();
    }
}
