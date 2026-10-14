<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Imports\StudentsImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class StudentImportController extends Controller
{
    /**
     * Figure 6 extension - Bulk import screen for Intern List.
     */
    public function create(): View
    {
        return view('coordinator.students.import');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
        ]);

        $coordinator = $request->user();

        $import = new StudentsImport($coordinator->campus_id);
        Excel::import($import, $request->file('file'));

        return redirect()
            ->route('coordinator.students.index')
            ->with('import_results', [
                'created' => $import->created,
                'skipped' => $import->skipped,
            ]);
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        $path = public_path('templates/student_import_template.csv');

        return response()->download($path, 'internlink-student-import-template.csv');
    }
}
