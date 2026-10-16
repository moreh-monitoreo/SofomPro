<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employment extends Model
{
    use HasFactory;

    protected $fillable = ['customer_id', 'type', 'area', 'main_activity', 'company', 'job', 'seniority_working', 'phone', 'imss', 'insurance_major_medical_expenses', 'monthly_income', 'other_income'];
}
