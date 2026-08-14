<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CampaignLead extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'lead_id',
        'status',
        'sent_at',
        'error_message',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }
}
