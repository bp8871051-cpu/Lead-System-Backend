<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScrapingResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'scraping_job_id',
        'raw_data',
    ];

    protected $casts = [
        'raw_data' => 'array',
    ];

    public function scrapingJob()
    {
        return $this->belongsTo(ScrapingJob::class);
    }
}
