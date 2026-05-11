<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Internship extends Model
{
    protected $fillable = [
        'candidate_id',
        'company_name',
        'duration',
        'supervisor_email',
        'certificate_path',
        'certificate_hash',
        'certificate_text',
        'verification_email_response',
        'status',
        'rejection_reason',
    ];
protected $appends = [
    'certificate_secure_url',
];

public function getCertificateSecureUrlAttribute()
{
    return url("/api/admin/files/internship-certificate/{$this->id}");
}

    public function candidate()
    {
        return $this->belongsTo(User::class, 'candidate_id');
    }
}
