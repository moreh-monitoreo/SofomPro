<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerAddress extends Model
{
    use HasFactory;

    protected $fillable = ['customer_id', 'street', 'interior_number', 'outdoor_number', 'suburb', 'postal_code', 'years_living', 'type'];
}
