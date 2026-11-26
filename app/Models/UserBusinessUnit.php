<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBusinessUnit extends Model
{
    use HasFactory;

    protected $table = 'user_business_units';

    protected $fillable = [
        'user_id',
        'business_unit_code',
        'can_edit',
    ];

    protected $casts = [
        'can_edit' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
