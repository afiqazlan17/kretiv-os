<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\LedgerEntry;
use App\Services\FinanceReports;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Indicative business income for the owner's income tax return (Borang B
// for a sole proprietor): net profit from the books, plus expenses that
// aren't deductible, less capital allowance on assets. A starting point for
// the tax agent, not a tax computation.
class TaxSummaryController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->seesCompanyFinance(), 403);

        $year = (int) $request->query('year', now()->year);

        return view('finance.tax', ['year' => $year, 'years' => range(now()->year, now()->year - 5)] + self::summary($year));
    }

    /** @return array<string, mixed> */
    public static function summary(int $year): array
    {
        $from = Carbon::create($year)->startOfYear();
        $to = Carbon::create($year)->endOfYear();
        $entries = LedgerEntry::whereBetween('date', [$from, $to])->get();
        $pl = (new FinanceReports($entries))->profitAndLoss();

        $live = $entries->where('reversed', false)->where('type', '!=', 'reversal');
        $nonDeductible = (float) $live->where('tax_treatment', 'non_deductible')->sum('amount');
        $partial = (float) $live->where('tax_treatment', 'partial')->sum('amount');
        $addBack = round($nonDeductible + $partial / 2, 2);

        $assets = Asset::whereYear('purchase_date', '<=', $year)->get();
        $capitalAllowance = round($assets->sum(fn (Asset $a) => $a->capitalAllowance($year)['allowance']), 2);

        return [
            'pl' => $pl,
            'nonDeductible' => $nonDeductible,
            'partial' => $partial,
            'addBack' => $addBack,
            'capitalAllowance' => $capitalAllowance,
            'assetsBought' => (float) $live->where('type', 'asset_purchase')->sum('amount'),
            'drawings' => (float) $live->where('type', 'owner_drawings')->sum('amount'),
            'adjustedIncome' => round($pl['net'] + $addBack - $capitalAllowance, 2),
        ];
    }
}
