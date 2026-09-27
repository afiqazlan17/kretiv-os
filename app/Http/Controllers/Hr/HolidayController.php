<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\PublicHoliday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Public holidays (Selangor + national) used for overtime rates; HR keeps
// the list right, e.g. adding a surprise holiday or fixing a moon-sighting date.
class HolidayController extends Controller
{
    public function index(Request $request): View
    {
        $year = (int) $request->query('year', now()->year);

        return view('hr.holidays', [
            'holidays' => PublicHoliday::whereYear('date', $year)->orderBy('date')->get(),
            'year' => $year,
            'canEdit' => $request->user()->canManageHr(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canManageHr(), 403);
        $data = $request->validate(['date' => ['required', 'date', 'unique:public_holidays,date'], 'name' => ['required', 'string', 'max:255']]);
        PublicHoliday::create($data);

        return back()->with('success', 'Holiday added.');
    }

    public function destroy(Request $request, PublicHoliday $holiday): RedirectResponse
    {
        abort_unless($request->user()->canManageHr(), 403);
        $holiday->delete();

        return back()->with('success', 'Holiday removed.');
    }
}
