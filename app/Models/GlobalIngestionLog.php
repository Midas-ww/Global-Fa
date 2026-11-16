<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GlobalIngestionLog extends Model
{
    use HasFactory;

    protected $table = 'global_ingestion_logs';

    protected $fillable = [
        'business_unit_code',
        'source_type',
        'filename',
        'records_count',
        'status',
        'details',
    ];
}
