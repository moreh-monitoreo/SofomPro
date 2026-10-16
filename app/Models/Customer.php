<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'status_id', 'email', 'phone', 'account_password', 'name', 'second_name', 'last_name', 'maiden_name', 'gender', 'birthday', 'state', 'country', 'rfc', 'curp', 'civil_status', 'level_study', 'own_car', 'economic_dependents'];

    // Nunca se serializa; el administrador la consulta solo desde el detalle del préstamo
    protected $hidden = ['account_password'];

    protected $appends = ['full_name'];

    public function getFullNameAttribute()
    {
        $full_name = $this->name;
        if ($this->second_name) {
            $full_name .= ' ' . $this->second_name;
        }
        if ($this->last_name) {
            $full_name .= ' ' . $this->last_name;
        }
        if ($this->maiden_name) {
            $full_name .= ' ' . $this->maiden_name;
        }
        return $full_name;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function addresses()
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    public function employment()
    {
        return $this->hasOne(Employment::class);
    }

    public function status()
    {
        return $this->belongsTo(Status::class);
    }
}
