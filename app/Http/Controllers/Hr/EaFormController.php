<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\EaRelease;
use App\Models\User;
use App\Services\EaForm;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

// Borang EA: HR checks the year's figures, then releases them; staff then
// download their own from My Payslips. Must reach staff by end of February.
class EaFormController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canManageHr(), 403);
        $year = (int) $request->query('year', now()->month <= 6 ? now()->year - 1 : now()->year);

        return view('hr.ea.index', [
            'year' => $year,
            'rows' => EaForm::staff($year)->map(fn (User $u) => ['user' => $u] + EaForm::build($u, $year)),
            'release' => EaRelease::where('year', $year)->first(),
        ]);
    }

    public function release(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canManageHr(), 403);
        $data = $request->validate(['year' => ['required', 'integer', 'min:2020', 'max:'.now()->year], 'undo' => ['nullable', 'boolean']]);

        if ($request->boolean('undo')) {
            EaRelease::where('year', $data['year'])->delete();

            return back()->with('success', "EA forms for {$data['year']} hidden from staff again.");
        }
        EaRelease::firstOrCreate(['year' => $data['year']], ['released_by' => $request->user()->name]);

        return back()->with('success', "EA forms for {$data['year']} released. Staff can download theirs from My Payslips.");
    }

    public function pdf(Request $request, User $user, int $year): Response
    {
        $viewer = $request->user();
        $own = $viewer->is($user) && EaRelease::where('year', $year)->exists();
        abort_unless($own || $viewer->canManageHr(), 403);

        $ea = EaForm::build($user, $year);
        abort_if($ea['months'] === 0, 404);

        // Signed off by whoever released the year (or the HR person printing it).
        $signer = $viewer->canManageHr() ? $viewer : User::where('name', EaRelease::where('year', $year)->value('released_by'))->first();

        return response(Pdf::loadView('hr.ea.pdf', ['ea' => $ea, 'signer' => $signer])->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="EA_'.$year.'_'.str_replace(' ', '_', $user->name).'.pdf"',
        ]);
    }
}
