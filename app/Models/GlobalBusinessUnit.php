<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GlobalBusinessUnit extends Model
{
    use HasFactory;

    protected $table = 'global_business_units';

    protected $fillable = [
        'code',
        'name',
        'description',
        'icon',
        'color',
        'data_source',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function entities(): HasMany
    {
        return $this->hasMany(GlobalEntity::class, 'business_unit_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(GlobalFinancialRecord::class, 'business_unit_code', 'code');
    }
}
