<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Referencia personal que el cliente da en su solicitud. */
class CustomerReference extends Model
{
    protected $fillable = ['customer_id', 'name', 'relationship', 'phone'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
