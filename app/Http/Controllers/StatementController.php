<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Job;
use App\Models\LedgerEntry;
use App\Support\Phone;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;

// Statement of Account: every invoice, payment and credit for one
// customer with a running balance and how old the unpaid amount is, as a
// PDF to send on WhatsApp (or a link the customer opens without logging in).
class StatementController extends Controller
{
    public function show(Request $request, Customer $customer): Response
    {
        abort_unless($request->user()->canManageFinance() || $request->user()->isBod(), 403);

        return $this->pdf($customer, $request->user()->shortName(), $request->boolean('download'));
    }

    /** Signed, expiring link for the customer. */
    public function shared(Customer $customer): Response
    {
        return $this->pdf($customer, null, false);
    }

    /** WhatsApp message with a 30-day link to the statement. */
    public static function whatsappUrl(Customer $customer, float $balance): string
    {
        $link = URL::temporarySignedRoute('statement.shared', now()->addDays(30), ['customer' => $customer->id]);
        $text = "Hi {$customer->name}, here is your statement of account from ".config('kretivco.brand.name')
            .'. Outstanding balance: RM '.number_format($balance, 2).".\n\n{$link}";

        return 'https://wa.me/'.Phone::whatsapp($customer->phone).'?text='.rawurlencode($text);
    }

    /** @return array{rows: array, balance: float, aging: array<string, float>} */
    public static function build(Customer $customer): array
    {
        $jobs = Job::where('customer_id', $customer->id)->get()->keyBy('job_id');
        $entries = LedgerEntry::whereIn('job_id', $jobs->keys())->where('reversed', false)
            ->whereIn('type', ['invoice', 'receipt', 'credit_note', 'deposit_refund'])
            ->orderBy('date')->orderBy('id')->get();

        $balance = 0.0;
        $rows = [];
        foreach ($entries as $e) {
            $charge = in_array($e->type, ['invoice', 'deposit_refund'], true) ? (float) $e->amount : 0.0;
            $paid = in_array($e->type, ['receipt', 'credit_note'], true) ? (float) $e->amount : 0.0;
            $balance += $charge - $paid;
            $rows[] = [
                'date' => $e->date,
                'ref' => $e->doc_number ?: '-',
                'job' => $e->job_id,
                'what' => ['invoice' => 'Invoice', 'receipt' => 'Payment received', 'credit_note' => 'Credit note', 'deposit_refund' => 'Deposit refunded'][$e->type].': '.($jobs[$e->job_id]->job_type ?? ''),
                'charge' => $charge, 'paid' => $paid, 'balance' => round($balance, 2),
            ];
        }

        // Age of what's still owed, invoice by invoice (oldest paid first).
        $aging = ['0-30' => 0.0, '31-60' => 0.0, '61-90' => 0.0, '90+' => 0.0];
        foreach ($entries->groupBy('job_id') as $group) {
            $invoice = $group->where('type', 'invoice')->sortByDesc('id')->first();
            if (! $invoice) {
                continue;
            }
            $owed = (float) $invoice->amount - (float) $group->whereIn('type', ['receipt', 'credit_note'])->sum('amount');
            if ($owed > 0.005) {
                $days = (int) $invoice->date->startOfDay()->diffInDays(today());
                $aging[$days <= 30 ? '0-30' : ($days <= 60 ? '31-60' : ($days <= 90 ? '61-90' : '90+'))] += round($owed, 2);
            }
        }

        return ['rows' => $rows, 'balance' => round($balance, 2), 'aging' => $aging];
    }

    private function pdf(Customer $customer, ?string $by, bool $download): Response
    {
        $data = self::build($customer);
        $name = 'SOA_'.$customer->customer_id.'_'.now()->format('Ymd').'.pdf';

        return response(Pdf::loadView('documents.statement', ['customer' => $customer, 'by' => $by] + $data)->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="'.$name.'"',
        ]);
    }
}
