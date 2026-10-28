<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/** A photo on the login page, managed by the OJT Coordinator (Campus Administration). */
class LoginSlide extends Model
{
    protected $fillable = ['path', 'caption', 'position', 'uploaded_by_user_id'];

    /** Where uploaded photos are kept (inside public/, so no storage link is needed). */
    public const DIR = 'uploads/login-slides';

    public function url(): string
    {
        return asset($this->path);
    }

    /** The shape of the login page's photo area (width : height); photos are cropped to it. */
    public const ASPECT_W = 5;
    public const ASPECT_H = 4;

    /** Is this one of the photos that came with the system (its file is never deleted)? */
    public function isBuiltIn(): bool
    {
        return ! str_starts_with($this->path, self::DIR.'/');
    }

    /**
     * Before the coordinator changes anything, list the built-in photos as
     * rows, so each one can be replaced, re-cropped, moved or removed on its own.
     */
    public static function listBuiltInsIfEmpty(): void
    {
        if (! Schema::hasTable('login_slides') || self::exists()) {
            return;
        }
        self::builtIn()->values()->each(fn ($p, $i) => self::create([
            'path' => $p['path'], 'caption' => $p['campus'], 'position' => $i + 1,
        ]));
    }

    /** @return Collection<int, array{path: string, src: string, campus: string}> */
    public static function builtIn(): Collection
    {
        return collect(glob(public_path('images/login-slides/*.{jpg,jpeg,png,webp}'), GLOB_BRACE))->sort()->values()
            ->map(function ($path) {
                $name = preg_replace('/^\d+[-_]/', '', pathinfo($path, PATHINFO_FILENAME));
                $label = $name === 'psu-campus' ? 'Pangasinan State University' : (string) \Illuminate\Support\Str::of($name)->replace(['-', '_'], ' ')->title();

                return ['path' => 'images/login-slides/'.basename($path), 'src' => asset('images/login-slides/'.basename($path)), 'campus' => $label];
            });
    }

    /**
     * The photos for the login page, in order: the coordinator's list, or
     * the photos that came with the system when none were uploaded yet.
     *
     * @return Collection<int, array{src: string, campus: string}>
     */
    public static function forLoginPage(): Collection
    {
        $uploaded = Schema::hasTable('login_slides')
            ? self::orderBy('position')->orderBy('id')->get()
                ->filter(fn ($s) => is_file(public_path($s->path)))
                ->map(fn ($s) => ['src' => $s->url(), 'campus' => (string) $s->caption])
                ->values()
            : collect();
        if ($uploaded->isNotEmpty()) {
            return $uploaded;
        }

        // Built-in photos. The caption comes from the file name: "02-bayambang-campus.jpg" -> "Bayambang Campus".
        $builtIn = self::builtIn()->map(fn ($p) => ['src' => $p['src'], 'campus' => $p['campus']]);

        return $builtIn->isNotEmpty() ? $builtIn : collect([['src' => asset('images/psu-main-campus.jpg'), 'campus' => 'Main Campus']]);
    }
}
