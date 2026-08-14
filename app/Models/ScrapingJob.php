<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScrapingJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'user_id',
        'job_number',
        'keyword',
        'location',
        'city',
        'state',
        'country',
        'requested_count',
        'rating_min',
        'rating_max',
        'website_filter',
        'has_email_filter',
        'has_phone_filter',
        'status',
        'apify_run_id',
        'leads_found',
        'leads_saved',
        'duplicates_found',
        'invalid_count',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'has_email_filter' => 'boolean',
        'has_phone_filter' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function results()
    {
        return $this->hasMany(ScrapingResult::class);
    }
}
