<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Objection extends Model
{
    use HasUuids;

    protected $fillable = [
        'niche_id', 'label', 'category', 'stage', 'context', 'times_raised',
        'times_resolved', 'what_worked', 'best_rebuttal', 'result', 'closer_id',
        'lead_id', 'call_id', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'times_raised' => 'integer',
            'times_resolved' => 'integer',
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

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function resolutionRate(): ?float
    {
        if ($this->times_raised < 3) {
            return null;
        }

        return round($this->times_resolved / $this->times_raised * 100, 1);
    }
}
