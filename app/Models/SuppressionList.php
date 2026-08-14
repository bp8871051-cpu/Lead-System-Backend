<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SuppressionList extends Model
{
    use HasFactory;

    protected $table = 'suppression_list';

    protected $fillable = [
        'company_id',
        'email',
        'reason',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
