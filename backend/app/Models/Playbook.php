<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Playbook extends Model
{
    protected $fillable = ['niche_id', 'name', 'description', 'criteria', 'version', 'active', 'created_by'];

    protected function casts(): array
    {
        return [
            'criteria' => 'array',
            'active' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function niche(): BelongsTo
    {
        return $this->belongsTo(Niche::class);
    }
}
