<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The record of an uploaded file is there but the file itself isn't on the
 * server (e.g. the hosting's own disk was reset by a new deploy, or the
 * database was copied without the files). Shows a clear page instead of a
 * bare "404 Not Found".
 */
class FileMissing extends Exception
{
    public function render(Request $request): Response
    {
        return response()->view('errors.file-missing', [], 404);
    }
}
