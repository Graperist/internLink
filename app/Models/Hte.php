<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hte extends Model
{
    protected $table = 'htes';

    protected $fillable = [
        'campus_id',
        'name',
        'address',
        'contact_person',
        'representative_position',
        'contact_email',
        'contact_phone',
        'moa_status',
        'latitude',
        'longitude',
        'geofence_radius_m',
        'max_gps_accuracy_m',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'geofence_radius_m' => 'float',
        'max_gps_accuracy_m' => 'float',
    ];

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function representatives()
    {
        return $this->hasMany(HteRepresentative::class);
    }

    public function deployments()
    {
        return $this->hasMany(Deployment::class);
    }
}
