<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Niche extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = ['slug', 'name', 'description', 'color', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}
