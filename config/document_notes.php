<?php

// Standard notes printed on each document, per department where the terms
// differ (from the Kretivco Documents & Portal Blueprint). Staff can still
// edit the notes on any single document; these are the starting wording.
// ':contact' is replaced with the company email and WhatsApp number.

$quotationValid = 'This quotation is valid for 14 days from the date of issue.';

return [
    'quotation' => [
        'print' => [
            "Prices are based on the specifications above. Any change to design, size, material or quantity after confirmation may change the price, and we'll send a revised quotation. Work starts once you confirm the order in writing.",
            'For orders below RM2,000, full payment is required before work begins. For orders of RM2,000 and above, an 80% deposit is required before the first draft.',
            'Production is completed within 14 working days after you approve the final draft.',
            'Artwork supplied by the client must be print-ready. Colours may vary slightly between screen and print. Final draft approval is the client\'s responsibility.',
            'Deposits are non-refundable once the booking is confirmed and the first draft has been prepared.',
            'Delivery charges apply as stated. Areas outside Klang Valley may be charged separately.',
            $quotationValid,
        ],
        'tech' => [
            'This quotation covers the scope of work listed above only. Additional features or changes will be quoted separately.',
            'Payment schedule: 50% deposit before work begins, 30% upon system demo/UAT, and 20% upon go-live.',
            'Timeline starts once the deposit and all required content/materials are received. Delays in client feedback may extend the delivery date.',
            'Up to 2 rounds of revision are included per milestone.',
            'Third-party costs (domain, hosting, WhatsApp API/Meta fees, SMS, payment gateway) are not included unless stated.',
            'Maintenance and support are available under a separate agreement. Free bug-fix warranty: 30 days after go-live.',
            'Ownership of the final system is transferred to the client upon full payment.',
            'Deposits are non-refundable once work has commenced.',
            $quotationValid,
        ],
        'brand' => [
            'This quotation covers the deliverables listed above. Additional items or changes in scope will be quoted separately.',
            'For orders below RM2,000, full payment is required before work begins. For orders of RM2,000 and above, a 50% deposit is required before work begins and the balance before final files are released.',
            'Up to 2 rounds of design revision are included. Additional revisions are charged separately.',
            'The client provides all content, text, photos and logos. Delays in content or feedback may extend the timeline.',
            'Final artwork files and usage rights are released to the client upon full payment.',
            'Stock images, fonts and music are licensed as needed. Licence fees, if any, are not included unless stated.',
            'Ad spend and platform boosting costs are not included.',
            'Kretivco reserves the right to feature completed work in its portfolio unless requested otherwise in writing.',
            'Deposits are non-refundable once work has begun.',
            $quotationValid,
        ],
        'event' => [
            'This quotation is based on the event date, venue, duration and scope stated above. Any change may affect the price and will be quoted in writing.',
            'The event date is confirmed only after the deposit is received. A 50% deposit is required to confirm the booking, and the balance is due 7 days before the event.',
            'Changes to the scope or quantity must be confirmed at least 7 days before the event.',
            'Venue rental, permits, licences, security and utilities are not included unless stated.',
            'Setup and dismantle are included within the agreed time. Overtime or venue restrictions may incur additional charges.',
            'For outdoor events, weather-related changes are not the responsibility of Kretivco. Rescheduling is subject to team and equipment availability.',
            'Cancellation: more than 30 days before the event, 50% of the deposit is refundable. 30 days or less, the deposit is non-refundable.',
            'The client is responsible for damage to rental equipment caused by guests or venue staff.',
            $quotationValid,
        ],
    ],

    'proforma' => [
        'default' => [
            'This is a proforma invoice issued to request payment before work begins. It is not a tax invoice.',
            'Work starts only after payment is received and confirmed.',
            'Payment is due within 7 days from the date of issue. The quotation may lapse if payment is not received.',
            'Please make payment to the bank account stated above and send proof of payment to :contact, quoting the proforma number as reference.',
            'A tax invoice will be issued for the balance (if any) or upon completion.',
            'Deposits are non-refundable as per the terms in the quotation.',
        ],
        'event' => [
            'This is a proforma invoice issued to request payment before work begins. It is not a tax invoice.',
            'Work starts only after payment is received and confirmed.',
            'Deposit is due within 7 days of issue to confirm the event date.',
            'Please make payment to the bank account stated above and send proof of payment to :contact, quoting the proforma number as reference.',
            'A tax invoice will be issued for the balance (if any) or upon completion.',
            'Deposits are non-refundable as per the terms in the quotation.',
        ],
    ],

    'invoice' => [
        'default' => [
            'Payment is due within 14 days from the invoice date unless otherwise agreed.',
            'Please make payment to the bank account stated above and send proof of payment to :contact.',
            'Please quote the invoice number as your payment reference.',
            'Any query regarding this invoice must be raised within 7 days of the invoice date.',
            'Late payment may delay production, delivery or handover of final files.',
        ],
        'tech' => [
            'Payment is due within 14 days from the invoice date unless otherwise agreed.',
            'Please make payment to the bank account stated above and send proof of payment to :contact.',
            'Please quote the invoice number as your payment reference.',
            'Any query regarding this invoice must be raised within 7 days of the invoice date.',
            'Late payment may delay production, delivery or handover of final files.',
            'System access will be released upon full payment.',
        ],
        'brand' => [
            'Payment is due within 14 days from the invoice date unless otherwise agreed.',
            'Please make payment to the bank account stated above and send proof of payment to :contact.',
            'Please quote the invoice number as your payment reference.',
            'Any query regarding this invoice must be raised within 7 days of the invoice date.',
            'Late payment may delay production, delivery or handover of final files.',
            'Final files will be released upon full payment.',
        ],
        'event' => [
            'Balance payment is due 7 days before the event date.',
            'Please make payment to the bank account stated above and send proof of payment to :contact.',
            'Please quote the invoice number as your payment reference.',
            'Any query regarding this invoice must be raised within 7 days of the invoice date.',
            'Late payment may delay production, delivery or handover of final files.',
        ],
    ],

    // Deposit when a balance is still owed after this payment, final when fully paid.
    'receipt_deposit' => [
        'default' => [
            'This receipt confirms deposit received for the quotation/proforma stated above.',
            'Cheque payments are valid only once the cheque is cleared.',
            'Deposits are non-refundable as per the terms in the quotation.',
            'Work schedule is confirmed from the date this deposit is received.',
            'Please keep this receipt for your records.',
        ],
    ],
    'receipt' => [
        'default' => [
            'This receipt confirms full payment received for the invoice stated above.',
            'Cheque payments are valid only once the cheque is cleared.',
            'Please keep this receipt for your records.',
        ],
    ],
];
