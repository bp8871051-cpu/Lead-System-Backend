<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'apify_api_token',
        'apify_actor_id',
        'ai_provider',
        'ai_api_key',
        'ai_model',
        'ai_temperature',
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',
        'smtp_encryption',
        'smtp_from_email',
        'smtp_from_name',
    ];

    protected $hidden = [
        'apify_api_token',
        'ai_api_key',
        'smtp_password',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
