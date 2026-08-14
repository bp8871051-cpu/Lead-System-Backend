<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneratedEmail extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'lead_id',
        'user_id',
        'campaign_id',
        'subject',
        'body',
        'tone',
        'length',
        'cta',
        'service_offered',
        'status',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }
}
