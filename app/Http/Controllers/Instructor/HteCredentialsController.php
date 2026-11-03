<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\HteListing;
use App\Models\HteRepresentative;
use App\Support\AuditLogger;
use App\Support\HteAccounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The mail icon: emails an HTE account its sign-in details (a new
 * password) - only once every intern at the agency has finished.
 * The OJT Coordinator can send for any agency on the campus; a Subject
 * Instructor for the agencies on their own program's partner list.
 */
class HteCredentialsController extends Controller
{
    public function send(Request $request, HteRepresentative $representative): RedirectResponse
    {
        $instructor = auth()->user()->instructor;
        $representative->load('user', 'hte');
        $hte = $representative->hte;

        abort_unless($hte && $hte->campus_id === $instructor->campus_id, 403);
        abort_unless($instructor->isCoordinator()
            || HteListing::where('hte_id', $hte->id)->where('program_id', $instructor->program_id)->exists(), 403);

        if ($representative->user->status === 'archived') {
            return back()->withErrors(['send' => "{$hte->name}'s account is archived. Restore it on HTE Accounts first."]);
        }

        $readiness = HteAccounts::readiness($hte);
        if (! $readiness['ready']) {
            return back()->withErrors(['send' => "Not sent yet - {$readiness['reason']} The sign-in details can be sent once every intern there has finished."]);
        }

        $result = HteAccounts::sendCredentials($representative);
        if (! $result['sent']) {
            return back()->withErrors(['send' => "The sign-in details for {$hte->name} couldn't be emailed: {$result['error']}"]);
        }

        AuditLogger::record('hte_account.credentials_sent', "Emailed the sign-in details of {$hte->name}'s HTE account to {$representative->user->email}.", auth()->user(), $request, $representative, $hte->campus_id);

        return back()->with('status', "Sign-in details for {$hte->name} were emailed to {$representative->user->email}.")
            ->with('warning', $hte->contactEmailWarning());
    }
}
