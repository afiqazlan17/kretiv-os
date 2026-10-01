<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Bahasa Melayu for the fixed wording on documents (titles, labels, totals).
 * What staff type (items, descriptions, edited notes) prints as typed; the
 * standard notes have their own BM set in config/document_notes_ms.php.
 */
class DocLang
{
    public const LANGS = ['en' => 'English', 'ms' => 'Bahasa Melayu'];

    private const MS = [
        // Titles and numbers
        'QUOTATION' => 'SEBUT HARGA',
        'PROFORMA INVOICE' => 'INVOIS PROFORMA',
        'INVOICE' => 'INVOIS',
        'PAYMENT RECEIPT' => 'RESIT BAYARAN',
        'DEPOSIT RECEIPT' => 'RESIT DEPOSIT',
        'DELIVERY ORDER' => 'NOTA PENGHANTARAN',
        'HANDOVER FORM' => 'BORANG SERAHAN',
        'CREDIT NOTE' => 'NOTA KREDIT',
        'QNo#' => 'No. Sebut Harga',
        'Proforma No#' => 'No. Proforma',
        'Invoice No#' => 'No. Invois',
        'Receipt No#' => 'No. Resit',
        'Ref No#' => 'No. Rujukan',
        'CN No#' => 'No. Nota Kredit',
        'Date' => 'Tarikh',
        'Phone:' => 'Tel:',
        'Valid until' => 'Sah sehingga',
        'Due' => 'Tarikh akhir',
        'By' => 'Oleh',
        // Customer block and title lines
        'Customer:' => 'Pelanggan:',
        'Title:' => 'Tajuk:',
        'PO No:' => 'No. PO:',
        'Payment for:' => 'Bayaran untuk:',
        'Against:' => 'Terhadap:',
        'Invoice' => 'Invois',
        // Items table
        'No' => 'Bil',
        'Description' => 'Keterangan',
        'Qty' => 'Kuantiti',
        'Unit Price' => 'Harga Seunit',
        'Amount' => 'Jumlah',
        // Totals
        'Subtotal' => 'Jumlah Kecil',
        'Delivery' => 'Penghantaran',
        'Discount' => 'Diskaun',
        'Total (MYR)' => 'Jumlah (MYR)',
        'Credit Amount (MYR)' => 'Jumlah Kredit (MYR)',
        'Less: Deposit Received' => 'Tolak: Deposit Diterima',
        'Balance Due (MYR)' => 'Baki Perlu Dibayar (MYR)',
        'Invoice Total' => 'Jumlah Invois',
        'Quotation Total' => 'Jumlah Sebut Harga',
        'Paid Before' => 'Telah Dibayar',
        'Payment Date' => 'Tarikh Bayaran',
        'Payment Method' => 'Kaedah Bayaran',
        'Amount Paid (MYR)' => 'Jumlah Dibayar (MYR)',
        // Footer
        'Note:' => 'Nota:',
        'Payment Detail:' => 'Butiran Bayaran:',
        'Scan to pay via DuitNow' => 'Imbas untuk bayar melalui DuitNow',
        'Issued by:' => 'Dikeluarkan oleh:',
        'Accepted by:' => 'Diterima oleh:',
        'Received by:' => 'Diterima oleh:',
        'Name:' => 'Nama:',
        'IC / Staff No:' => 'No. KP / Pekerja:',
        'Signature & company stamp' => 'Tandatangan & cop syarikat',
        'Page {PAGE_NUM} of {PAGE_COUNT}' => 'Muka surat {PAGE_NUM} daripada {PAGE_COUNT}',
        // Payment methods
        'Bank Transfer' => 'Pindahan Bank',
        'Cash' => 'Tunai',
        'Online Banking' => 'Perbankan Dalam Talian',
        'General:' => 'Umum:',
    ];

    private const MONTHS = ['Jan' => 'Jan', 'Feb' => 'Feb', 'Mar' => 'Mac', 'Apr' => 'Apr', 'May' => 'Mei', 'Jun' => 'Jun', 'Jul' => 'Jul', 'Aug' => 'Ogo', 'Sep' => 'Sep', 'Oct' => 'Okt', 'Nov' => 'Nov', 'Dec' => 'Dis'];

    public static function valid(?string $lang): string
    {
        return array_key_exists((string) $lang, self::LANGS) ? $lang : 'en';
    }

    public static function t(string $text, ?string $lang): string
    {
        return $lang === 'ms' ? (self::MS[$text] ?? $text) : $text;
    }

    /** "01 Oct 2026", or "01 Okt 2026" in BM. */
    public static function date(Carbon|string $date, ?string $lang): string
    {
        $text = Carbon::parse($date)->format('d M Y');

        return $lang === 'ms' ? strtr($text, self::MONTHS) : $text;
    }
}
