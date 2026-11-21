<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GlobalIntercompanyRule extends Model
{
    use HasFactory;

    protected $table = 'global_intercompany_rules';

    protected $fillable = [
        'name',
        'from_entity_code',
        'to_entity_code',
        'category',
        'source_report_type',
        'source_category',
        'account_name_pattern',
        'description_template',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
