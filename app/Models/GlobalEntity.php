<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GlobalEntity extends Model
{
    use HasFactory;

    protected $table = 'global_entities';

    protected $fillable = [
        'business_unit_id',
        'code',
        'name',
        'location',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(GlobalBusinessUnit::class, 'business_unit_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(GlobalFinancialRecord::class, 'entity_code', 'code');
    }
}
