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
            // The HTE account follows the agency's contact email, even one another account uses.
            $user->update(['email' => $email]);
        }
    }

    /**
     * The other account that already uses this agency's contact email (so
     * the HTE account can't take it and keeps its old address), or null.
     */
    public function contactEmailTakenBy(): ?User
    {
        // HTE accounts may share an email with another account, so nothing blocks it.
        return null;
    }

    /** A plain sentence for the coordinator when the contact email is taken. */
    public function contactEmailWarning(): ?string
    {
        $owner = $this->contactEmailTakenBy();
        if (! $owner || ! $this->representatives()->exists()) {
            return null;
        }
        $current = $this->representatives()->with('user')->first()?->user?->email;

        return "{$this->contact_email} is already used by another PSU Intern-Link account ({$owner->name}, "
            .($owner->role?->name ?? 'another role').'), so the HTE account can\'t use it'
            .($current ? " and still sends to {$current}" : '').'. Give the agency an email no other account uses.';
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
