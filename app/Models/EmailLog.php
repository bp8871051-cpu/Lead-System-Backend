<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'lead_id',
        'campaign_id',
        'subject',
        'provider',
        'sender_email',
        'recipient_email',
        'status',
        'message_id',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }
}
