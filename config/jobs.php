<?php

// Jobs-module lookup data. Mirrors the corresponding keys from
// jobs.kretiv.co's config/kretivco.php — shared 'departments'/'roles'
// moved to config/kretivos.php, Finance-only keys (brand, bank_details,
// expense_categories) deferred to Phase 3.
return [

    'job_types' => [
        'client_project' => ['label' => 'Client Project', 'color' => '#3A86FF', 'desc' => 'Kretivco does custom work for the customer'],
        'product_sale' => ['label' => 'Product Sale', 'color' => '#10B981', 'desc' => 'Customer buys an existing Kretivco product'],
    ],

    'banks' => [
        'mbb' => ['label' => 'Maybank', 'code' => 'MBB', 'color' => '#FFC107'],
        'affin' => ['label' => 'AFFIN', 'code' => 'AFFIN', 'color' => '#E53935'],
    ],

    // A job stays "potential" while it's still a quotation (nothing
    // confirmed yet). Once the customer confirms, staff claim it ("Take In
    // Job") — that single action sets the PIC and moves it straight to
    // "in_progress". It stays there for the whole time the work is
    // actually happening, and moves to "completed" when staff close the
    // ticket.
    'job_statuses' => [
        'potential' => ['label' => 'Potential', 'color' => '#6366F1'],
        'in_progress' => ['label' => 'In Progress', 'color' => '#3A86FF'],
        'completed' => ['label' => 'Completed', 'color' => '#6B7280'],
        'cancelled' => ['label' => 'Cancelled', 'color' => '#EF4444'],
    ],

    'cancel_reasons' => [
        'customer_cancelled' => 'Customer cancelled',
        'budget_issue' => 'Budget issue',
        'scope_changed' => 'Scope changed',
        'no_response' => 'No response',
        'other' => 'Other',
    ],

    'sources' => [
        'tender' => 'Tender',
        'referral' => 'Referral',
        'walk-in' => 'Walk-in',
        'social_media' => 'Social Media',
        'website' => 'Website',
        'other' => 'Other',
    ],

    // A sole-proprietor still registers an SSM number, so "has SSM" is
    // what actually distinguishes a company from a walk-in/personal
    // customer here — not company size.
    'customer_types' => [
        'individual' => 'Individual',
        'company' => 'Company',
    ],

    'vendor_categories' => [
        'printing' => 'Printing',
        'delivery' => 'Delivery / Logistics',
        'design_freelance' => 'Design / Freelance',
        'event_equipment' => 'Event & Equipment',
        'subcontractor' => 'Subcontractor',
        'other' => 'Other',
    ],

    'lead_stages' => [
        'new' => ['label' => 'New', 'color' => '#6366F1'],
        'contacted' => ['label' => 'Contacted', 'color' => '#F59E0B'],
        'quoted' => ['label' => 'Quoted', 'color' => '#3A86FF'],
        'won' => ['label' => 'Won', 'color' => '#10B981'],
        'lost' => ['label' => 'Lost', 'color' => '#EF4444'],
    ],

];
