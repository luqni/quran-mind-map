<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MindMapNode extends Model
{
    protected $guarded = [];

    public function surah(): BelongsTo
    {
        return $this->belongsTo(Surah::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MindMapNode::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MindMapNode::class, 'parent_id');
    }
}
