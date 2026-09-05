<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SosReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'sos_alert_id',
        'reporter_id',
        'reason',
        'description',
    ];

    public function sosAlert()
    {
        return $this->belongsTo(SosAlert::class);
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }
}
