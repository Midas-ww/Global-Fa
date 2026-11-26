<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlobalPeriodNote extends Model
{
    use HasFactory;

    protected $table = 'global_period_notes';

    protected $fillable = [
        'period',
        'business_unit_code',
        'report_type',
        'closing_status',
        'notes',
        'user_id',
        'user_name',
    ];

    protected $casts = [
        'period' => 'string',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Label deskriptif untuk status closing.
     */
    public function getClosingStatusLabelAttribute(): string
    {
        return match ($this->closing_status) {
            'closed' => 'Sudah Selesai',
            'unclosed', 'draft' => 'Belum Closing',
            default => '-',
        };
    }
}
