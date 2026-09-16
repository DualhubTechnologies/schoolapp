<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class School extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'motto',
        'description',
        'unique_code',
        'email',
        'nssf_employer_number',
        'tin_number',
        'phone',
        'website',
        'address',
        'city',
        'country',
        'logo',
        'hm_signature',
        'timezone',
        'currency',
        'status',
    ];
}