<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public const STAGES = [
        'lead_agendado',
        'fup',
        'remarcado',
        'negociacao',
        'fechado',
        'loss',
        'mal_qualificado',
    ];

    public const STAGE_LABELS = [
        'lead_agendado' => 'Lead Agendado',
        'fup' => 'FUP',
        'remarcado' => 'Remarcado',
        'negociacao' => 'Negociação',
        'fechado' => 'Fechado',
        'loss' => 'Loss',
        'mal_qualificado' => 'Mal Qualificado',
    ];

    protected $fillable = [
        'name', 'company', 'phone', 'whatsapp', 'email', 'niche_id', 'origin',
        'stage', 'temperature', 'priority', 'position', 'closer_id', 'pre_sales_id',
        'ticket_estimado', 'valor_negociado', 'ticket_fechado', 'receita_prevista',
        'first_contact_at', 'last_interaction_at', 'next_action_at', 'meeting_at', 'last_stage_change_at',
        'next_steps', 'loss_reason', 'disqualify_reason', 'call_id', 'saldo_devedor',
        'parcelas', 'custom_fields', 'notes', 'commission_percentage', 'commission_amount',
    ];

    protected function casts(): array
    {
        return [
            'ticket_estimado' => 'decimal:2',
            'valor_negociado' => 'decimal:2',
            'ticket_fechado' => 'decimal:2',
            'receita_prevista' => 'decimal:2',
            'saldo_devedor' => 'decimal:2',
            'commission_percentage' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'priority' => 'integer',
            'position' => 'integer',
            'first_contact_at' => 'datetime',
            'last_interaction_at' => 'datetime',
            'next_action_at' => 'datetime',
            'meeting_at' => 'datetime',
            'last_stage_change_at' => 'datetime',
            'next_steps' => 'array',
            'parcelas' => 'array',
            'custom_fields' => 'array',
        ];
    }

    public function niche(): BelongsTo
    {
        return $this->belongsTo(Niche::class);
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closer_id');
    }

    public function preSales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pre_sales_id');
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(LeadInteraction::class)->latest('occurred_at');
    }

    public function stageHistories(): HasMany
    {
        return $this->hasMany(LeadStageHistory::class)->latest();
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    public function calls(): HasMany
    {
        return $this->hasMany(Call::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(LeadAttachment::class);
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $inner) use ($user) {
            $inner->where('closer_id', $user->getKey())
                ->orWhere('pre_sales_id', $user->getKey());
        });
    }
}
