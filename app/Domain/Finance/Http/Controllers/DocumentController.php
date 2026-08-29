<?php

namespace App\Domain\Finance\Http\Controllers;

use App\Domain\Finance\Services\LedgerService;
use App\Domain\Jobs\Models\Job;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

// Generates Invoice/Receipt PDFs and posts the matching ledger entry via
// LedgerService — the Laravel dompdf equivalent of the old app's jsPDF
// generator (lib/pdf-generator.js). Routed from the Jobs subdomain
// (routes/jobs.php) since it's triggered from a job's own page, but the
// controller and the ledger it posts to both live in the Finance module —
// this is the concrete Job -> Finance cross-module call the module split
// exists to prove. The full Kretivco letterhead layout (logo/exact
// coordinates) is deferred; this covers the document content and,
// critically, the ledger posting the document depends on.
class DocumentController extends Controller
{
    public function invoice(Request $request, Job $job, LedgerService $ledger)
    {
        $this->authorize('update', $job);

        $docNumber = 'INV-'.now()->year.'-'.str_pad((string) preg_replace('/\D/', '', $job->job_id) ?: '001', 3, '0', STR_PAD_LEFT);
        $entry = $ledger->postInvoiceEntry($job, $docNumber, $request->user()->name);

        return $this->renderPdf('invoice', $job, $entry, $docNumber);
    }

    public function receipt(Request $request, Job $job, LedgerService $ledger)
    {
        $this->authorize('update', $job);

        $docNumber = 'RC-'.now()->year.'-'.str_pad((string) preg_replace('/\D/', '', $job->job_id) ?: '001', 3, '0', STR_PAD_LEFT);
        $entry = $ledger->postReceiptEntry($job, $docNumber, $request->user()->name);

        return $this->renderPdf('receipt', $job, $entry, $docNumber);
    }

    private function renderPdf(string $type, Job $job, $entry, string $docNumber): RedirectResponse|Response
    {
        if (! $entry) {
            return back()->with('success', 'Nothing to post — amount is empty.');
        }

        $pdf = Pdf::loadView('documents.pdf', [
            'type' => $type,
            'job' => $job,
            'entry' => $entry,
            'docNumber' => $docNumber,
        ]);

        return $pdf->download("{$docNumber}_{$job->job_id}.pdf");
    }
}
