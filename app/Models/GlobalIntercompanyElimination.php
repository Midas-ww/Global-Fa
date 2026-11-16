<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GlobalIntercompanyElimination extends Model
{
    use HasFactory;

    protected $table = 'global_intercompany_eliminations';

    protected $fillable = [
        'period',
        'from_entity_code',
        'to_entity_code',
        'category',
        'description',
        'amount',
        'is_active',
    ];

    protected $casts = [
        'period' => 'date',
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
