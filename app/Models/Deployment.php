<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Deployment extends Model
{
    protected static function booted(): void
    {
        // Once the student is actually deployed (status becomes active), the
        // "ready for deployment" notice is resolved. Deployments are now
        // created as soon as the application is accepted, so creation alone
        // no longer means the student is deployed.
        static::saved(function (Deployment $deployment) {
            if ($deployment->status !== 'active') {
                return;
            }
            if (! $deployment->wasRecentlyCreated && ! $deployment->wasChanged('status')) {
                return;
            }
            $studentId = $deployment->internship?->student_id;
            if ($studentId) {
                \App\Models\Notification::resolve('predeploy:'.$studentId);
            }
        });
    }

    protected $fillable = ['internship_id', 'hte_id', 'start_date', 'end_date', 'status'];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];

    public function internship()
    {
        return $this->belongsTo(Internship::class);
    }

    public function hte()
    {
        return $this->belongsTo(Hte::class);
    }

    // "generates" - all DTR entries logged under this deployment
    public function dtrs()
    {
        return $this->hasMany(Dtr::class);
    }

    public function latestDtr()
    {
        return $this->hasOne(Dtr::class)->latestOfMany('log_date');
    }

    // "results_in" - the evaluation tied to this deployment
    public function evaluation()
    {
        return $this->hasOne(Evaluation::class);
    }
}
