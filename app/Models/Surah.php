<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Surah extends Model
{
    protected $guarded = [];

    public function nodes(): HasMany
    {
        return $this->hasMany(MindMapNode::class);
    }
}
