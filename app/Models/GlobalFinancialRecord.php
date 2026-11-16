<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlobalFinancialRecord extends Model
{
    use HasFactory;

    protected $table = 'global_financial_records';

    protected $fillable = [
        'business_unit_code',
        'entity_code',
        'period',
        'report_type',
        'category',
        'sub_category',
        'account_code',
        'account_name',
        'amount',
        'status',
        'source_type',
        'notes',
    ];

    protected $casts = [
        'period' => 'date',
        'amount' => 'decimal:2',
    ];

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(GlobalBusinessUnit::class, 'business_unit_code', 'code');
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(GlobalEntity::class, 'entity_code', 'code');
    }
}
