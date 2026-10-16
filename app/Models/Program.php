<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    protected $fillable = ['campus_id', 'code', 'name', 'major', 'required_hours'];

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }

    public function instructors()
    {
        return $this->hasMany(Instructor::class);
    }

    /**
     * Course title as printed on the official forms, e.g. "Bachelor of
     * Science in Business Administration". Older rows stored the major in
     * the name ("... - Operations Management"); that suffix is dropped.
     */
    public function courseTitle(): string
    {
        $name = (string) $this->name;

        if (blank($this->major) && str_contains($name, ' - ')) {
            return trim(strstr($name, ' - ', true));
        }

        return $name;
    }

    /** Major as printed on the forms; "N/A" when the course has none. */
    public function majorLabel(): string
    {
        if (filled($this->major)) {
            return $this->major;
        }

        // Seeded Business Administration rows predate the major column.
        return match ($this->code) {
            'BSBA-OM' => 'OM',
            'BSBA-FM' => 'FM',
            default => 'N/A',
        };
    }

    /** "BSBA-MM · Business Administration, major in Marketing Management" style label. */
    public function displayName(): string
    {
        $major = $this->majorLabel();

        return $this->courseTitle().($major !== 'N/A' ? ' - '.$major : '');
    }
}
