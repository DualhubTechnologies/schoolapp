<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class School extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'email',
        'nssf_employer_number',
        'tin_number',
        'phone',
        'address',
        'city',
        'country',
        'logo',
        'timezone',
        'currency',
        'status',
    ];
}