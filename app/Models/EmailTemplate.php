<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'subject',
        'body',
        'service',
        'tone',
        'variables',
        'is_default',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_default' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
