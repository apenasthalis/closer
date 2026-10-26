<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Call extends Model
{
    use HasUuids;

    protected $fillable = [
        'lead_id', 'closer_id', 'title', 'status', 'started_at', 'duration_sec', 'transcript',
        'summary', 'spin', 'objections', 'talk_time_closer', 'talk_time_lead',
        'adherence_score', 'playbook_score', 'analysis', 'analysis_status', 'ai_provider',
        'analyzed_at', 'recording_path', 'recording_disk', 'sentiment', 'highlights',
        'extracted_steps',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'analyzed_at' => 'datetime',
            'transcript' => 'array',
            'spin' => 'array',
            'objections' => 'array',
            'analysis' => 'array',
            'sentiment' => 'array',
            'highlights' => 'array',
            'extracted_steps' => 'array',
            'talk_time_closer' => 'float',
            'talk_time_lead' => 'float',
            'duration_sec' => 'integer',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closer_id');
    }
}
