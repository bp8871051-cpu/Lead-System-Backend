<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'service',
        'email_template_id',
        'subject',
        'daily_sending_limit',
        'sending_provider',
        'scheduled_at',
        'status',
        'total_leads',
        'sent_count',
        'failed_count',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'daily_sending_limit' => 'integer',
        'total_leads' => 'integer',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function template()
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }

    public function campaignLeads()
    {
        return $this->hasMany(CampaignLead::class);
    }

    public function emailLogs()
    {
        return $this->hasMany(EmailLog::class);
    }
}
