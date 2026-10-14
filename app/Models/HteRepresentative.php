<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HteRepresentative extends Model
{
    protected $fillable = ['user_id', 'hte_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function hte()
    {
        return $this->belongsTo(Hte::class);
    }

    // "verifies" - DTR entries this representative has checked
    public function verifiedDtrs()
    {
        return $this->hasMany(Dtr::class, 'verified_by');
    }

    // "submits" - evaluations this representative filed
    public function evaluations()
    {
        return $this->hasMany(Evaluation::class);
    }
}
