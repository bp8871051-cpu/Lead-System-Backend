<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'business_name',
        'contact_name',
        'category',
        'email',
        'secondary_email',
        'phone',
        'secondary_phone',
        'whatsapp_number',
        'website',
        'website_status',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'google_maps_url',
        'latitude',
        'longitude',
        'google_rating',
        'review_count',
        'source',
        'source_id',
        'tags',
        'notes',
        'lead_status',
        'email_status',
        'phone_status',
        'outreach_status',
    ];

    protected $casts = [
        'tags' => 'array',
        'latitude' => 'float',
        'longitude' => 'float',
        'google_rating' => 'float',
        'review_count' => 'integer',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function notes()
    {
        return $this->hasMany(LeadNote::class)->latest();
    }

    public function campaignLeads()
    {
        return $this->hasMany(CampaignLead::class);
    }

    public function generatedEmails()
    {
        return $this->hasMany(GeneratedEmail::class);
    }

    public function emailLogs()
    {
        return $this->hasMany(EmailLog::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }
}
