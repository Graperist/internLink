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

    /**
     * The partner agency's contact email is where its HTE account's sign-in
     * details go, so changing it (on Partner Agencies) updates the account's
     * email too.
     */
    protected static function booted(): void
    {
        static::updated(function (Hte $hte) {
            if ($hte->wasChanged('contact_email')) {
                $hte->syncRepresentativeEmails();
            }
        });
    }

    /**
     * Gives the HTE account(s) the agency's current contact email, unless
     * it's blank or another account already uses that address.
     */
    public function syncRepresentativeEmails(): void
    {
        $email = trim((string) $this->contact_email);
        if ($email === '') {
            return;
        }

        foreach ($this->representatives()->with('user')->get() as $representative) {
            $user = $representative->user;
            if (! $user || strcasecmp((string) $user->email, $email) === 0) {
                continue;
            }
            $taken = User::where('email', $email)->where('id', '!=', $user->id)->exists();
            if (! $taken) {
                $user->update(['email' => $email]);
            }
        }
    }

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

    /** Partner Agency repository rows (program + term) for this HTE. */
    public function listings()
    {
        return $this->hasMany(HteListing::class);
    }
}
