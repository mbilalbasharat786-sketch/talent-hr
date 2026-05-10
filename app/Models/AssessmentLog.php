<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentLog extends Model
{
    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    protected $fillable = [
        'session_id',
        'event_type',
        'event_time',
        'metadata',
    ];

    protected $casts = [
        'event_time' => 'datetime',
        'metadata' => 'array',
    ];

    public function session()
    {
        return $this->belongsTo(AssessmentSession::class, 'session_id');
    }
}
