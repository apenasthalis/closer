<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposalVersion extends Model
{
    use HasUuids;

    protected $fillable = ['proposal_id', 'version', 'snapshot', 'created_by'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'version' => 'integer'];
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }
}
