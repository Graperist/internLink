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
    public function index(Request $request): View
    {
        $campusId = auth()->user()->campus_id;
        $query = Hte::where('campus_id', $campusId)->withCount([
            'deployments as active_interns_count' => fn ($q) => $q->where('status', 'active'),
            'deployments as total_interns_count',
        ]);

        if ($search = $request->query('search')) $query->where('name', 'like', "%{$search}%");
        if ($status = $request->query('moa_status')) $query->where('moa_status', $status);

        $htes = $query->orderBy('name')->paginate(20)->withQueryString();
        return view('coordinator.htes.index', compact('htes'));
    }

    public function create(): View { return view('coordinator.htes.create'); }

    public function store(StoreHteRequest $request): RedirectResponse
    {
        Hte::create(['campus_id' => auth()->user()->campus_id, ...$request->validated()]);
        return redirect()->route('coordinator.htes')->with('status', 'Partner HTE added with DTR location security settings.');
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
        return redirect()->route('coordinator.htes')->with('status', $hte->name.' was updated.');
    }
}
