<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportMedia extends Model
{
    protected $table = 'report_media';

    protected $fillable = [
        'report_id',
        'type',
        'media_url',
        'media_hash',
        'fingerprint_hash',
    ];

    public function report()
    {
        return $this->belongsTo(Report::class, 'report_id');
    }
}
