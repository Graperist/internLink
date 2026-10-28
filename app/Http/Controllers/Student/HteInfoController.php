<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class HteInfoController extends Controller
{
    /**
     * Figure 23 - My HTE. The weekly schedule table in the mockup
     * (Mon-Fri 8-5, Saturday rest day) isn't real data - there's no
     * schedule field in the schema, so it's omitted rather than faked.
     *
     * The host training establishment's evaluations of the student (Forms 13
     * and 21) go to the Subject Instructor only, so they are not shown here.
     */
    public function index(): View
    {
        $student = auth()->user()->student;
        $deployment = $student->currentInternship?->deployment;
        // The student's evaluations of the agency (Forms 23c, 20) are made here.
        $evaluation = EvaluationController::data($student);

        return view('student.hte', compact('deployment', 'evaluation'));
    }
}
