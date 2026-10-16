<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    use HasFactory;

    protected $fillable = ['type', 'policy_folio', 'customer_id', 'amount', 'purpose', 'purpose_description', 'term', 'score', 'approved_at', 'reject_at', 'start_at', 'paid_at'];

    protected $appends = ['status'];

    public function getStatusAttribute()
    {
        if (!$this->approved_at && !$this->reject_at) {
            return 'Pendiente';
        }
        if ($this->paid_at) {
            return 'Pagado';
        }
        if ($this->approved_at) {
            return 'Aprobado';
        } else {
            return 'Rechazado';
        }
    }
    
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function documentations()
    {
        return $this->hasMany(Documentation::class);
    }
}
