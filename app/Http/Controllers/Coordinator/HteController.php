<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Coordinator\StoreHteRequest;
use App\Models\Hte;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HteController extends Controller
{
    /** The list itself is the shared Partner Agencies module. */
    public function index(): RedirectResponse
    {
        return redirect()->route('instructor.htes');
    }

    public function create(): View { return view('coordinator.htes.create'); }

    public function store(StoreHteRequest $request): RedirectResponse
    {
        Hte::create(['campus_id' => auth()->user()->campus_id, ...$request->validated()]);
        return redirect()->route('instructor.htes')->with('status', 'Partner HTE added with DTR location security settings.');
    }

    public function edit(Hte $hte): View
    {
        abort_unless($hte->campus_id === auth()->user()->campus_id, 403);
        return view('coordinator.htes.edit', compact('hte'));
    }

    public function update(StoreHteRequest $request, Hte $hte): RedirectResponse
    {
        abort_unless($hte->campus_id === auth()->user()->campus_id, 403);
        $hte->update($request->validated());
        return redirect()->route('instructor.htes')->with('status', $hte->name.' was updated.');
    }
}
