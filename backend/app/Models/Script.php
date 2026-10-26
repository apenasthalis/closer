<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Script extends Model
{
    use HasUuids;

    protected $fillable = [
        'niche_id', 'title', 'description', 'stage', 'category', 'sections',
        'version', 'status', 'playbook_id', 'updated_by', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'sections' => 'array',
            'published_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    public function niche(): BelongsTo
    {
        return $this->belongsTo(Niche::class);
    }

    public function playbook(): BelongsTo
    {
        return $this->belongsTo(Playbook::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
