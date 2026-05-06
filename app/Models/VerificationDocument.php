<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerificationDocument extends Model
{
    protected $fillable = [
        'company_id',
        'type',
        'file_path',
        'status',
    ];

    protected $appends = [
        'secure_url',
    ];

    public function getSecureUrlAttribute()
    {
        if (request()->user()?->role === 'company') {
            return url("/api/company/documents/{$this->id}/file");
        }

        return url("/api/admin/files/company-document/{$this->id}");
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}

