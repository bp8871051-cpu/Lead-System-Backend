<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'logo',
        'primary_color',
        'description',
        'industry',
        'services',
        'products',
        'website',
        'phone',
        'alternate_phone',
        'email',
        'address',
        'city',
        'state',
        'country',
        'gst_number',
        'cin_number',
        'business_hours',
        'privacy_policy_url',
        'terms_url',
        'target_audience',
        'target_industries',
        'target_locations',
        'usp',
        'company_tone',
        'email_signature',
        'default_sender_name',
        'default_sender_designation',
        'default_sender_email',
        'social_links',
    ];

    protected $casts = [
        'services' => 'array',
        'products' => 'array',
        'target_industries' => 'array',
        'target_locations' => 'array',
        'social_links' => 'array',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function settings()
    {
        return $this->hasOne(CompanySetting::class);
    }

    public function leads()
    {
        return $this->hasMany(Lead::class);
    }

    public function scrapingJobs()
    {
        return $this->hasMany(ScrapingJob::class);
    }

    public function emailTemplates()
    {
        return $this->hasMany(EmailTemplate::class);
    }

    public function campaigns()
    {
        return $this->hasMany(Campaign::class);
    }

    public function generatedEmails()
    {
        return $this->hasMany(GeneratedEmail::class);
    }

    public function emailLogs()
    {
        return $this->hasMany(EmailLog::class);
    }

    public function suppressionList()
    {
        return $this->hasMany(SuppressionList::class);
    }

    public function importLogs()
    {
        return $this->hasMany(ImportLog::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }
}
