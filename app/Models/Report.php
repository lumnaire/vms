<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $fillable = [
        'generated_by',
        'report_type',
        'report_date',
        'report_data',
    ];

    protected $casts = [
        'report_date' => 'date',
        'report_data' => 'array',
    ];

    public function generatedBy()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}