<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Documentation extends Model
{
    protected $fillable = ['loan_id', 'name', 'type', 'path', 'uploaded_by'];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }
}
