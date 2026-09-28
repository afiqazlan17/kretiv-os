<?php

// Mirrors lib/constants.js from the old Next.js app — the canonical
// lookup data referenced from Blade views and controllers.
return [

    'departments' => [
        'print' => ['label' => 'KretivPrint', 'color' => '#E85D04'],
        'brand' => ['label' => 'KretivBrand', 'color' => '#7209B7'],
        'tech' => ['label' => 'KretivTech', 'color' => '#3A86FF'],
        'event' => ['label' => 'KretivEvent', 'color' => '#E91E63'],
    ],

    // Company units that aren't Jobs business units: staff can belong to
    // them (HR, org chart) but they never appear in job or revenue lists.
    'support_units' => [
        'admin' => ['label' => 'Finance & Admin', 'color' => '#0F766E'],
    ],

    // Starting point for the HR Departments page (copied into the
    // departments table on migrate; HR edits it there from then on).
    // Was: static "who does what" profile shown on the Departments page — copied
    // from the live app. Keys match `departments` above.
    'department_profiles' => [
        'print' => [
            'lead' => 'Nurfadilah (Interim)',
            'services' => [
                'Large Format: banner, bunting, backdrop',
                'Small Format: business card, flyer, brochure, menu card',
                'Corporate Gifts & Souvenirs',
                'Packaging & Label',
                'Digital & Offset Printing',
            ],
            'note' => 'Minor edit = KretivPrint handle. Custom design = loop KretivBrand.',
        ],
        'brand' => [
            'lead' => 'Afiq Azlan (Interim)',
            'services' => ['Brand Strategy & Identity', 'Social Media Management', 'Content & Design', 'Websites That Convert'],
        ],
        'tech' => [
            'lead' => 'Amnan Syahmi',
            'services' => ['Website Creation', 'Application Development', 'Sales Page / Landing Page'],
            'products' => ['Undangan.my: Digital Wedding Invitation', 'Restu.ai: Digital Wedding Planner', 'Wedding Planner by Ila (Notion-based)'],
        ],
        'event' => [
            'lead' => 'Afiq Azlan (Interim)',
            'services' => [
                'Event Planning & Coordination',
                'Vendor Management',
                'Event Decoration & Setup',
                'Emcee & Stage Performance',
                'Corporate Events, Official Functions, Product Launch',
            ],
        ],
    ],

    // Kretiv OS: one app, several modules. Each module can live on its own
    // subdomain (set the *_HOST env vars); with none set the app runs on a
    // single host exactly as before (local dev, tests).
    'hosts' => [
        'os' => env('OS_HOST'),
        'jobs' => env('JOBS_HOST'),
        'finance' => env('FINANCE_HOST'),
        'hr' => env('HR_HOST'),
    ],

    'modules' => [
        'jobs' => ['label' => 'Jobs', 'icon' => 'briefcase', 'desc' => 'Job queue, customers, quotations and invoices'],
        'finance' => ['label' => 'Finance', 'icon' => 'coin', 'desc' => 'Ledger, vendor payments and reports'],
        'hr' => ['label' => 'HR', 'icon' => 'users', 'desc' => 'Leave, announcements and payslips'],
    ],

    // What a user can open until BOD saves an explicit list for them.
    // BOD always has every module.
    'module_defaults' => [
        'bod' => ['jobs', 'finance', 'hr'],
        'dept_head' => ['jobs', 'finance', 'hr'],
        'staff' => ['jobs', 'hr'],
        'intern' => ['jobs', 'hr'],
        'finance' => ['finance', 'hr'],
        'hr' => ['hr'],
    ],

    'roles' => [
        'bod' => ['label' => 'BOD', 'full' => 'Board Of Directors', 'color' => '#E91E63', 'desc' => 'Full access: all departments, reports, settings'],
        'dept_head' => ['label' => 'Dept Head', 'full' => 'Head of Department', 'color' => '#3A86FF', 'desc' => 'Own department(s): jobs, reports'],
        'staff' => ['label' => 'Staff', 'color' => '#6B7280', 'desc' => 'Own department(s): jobs, no reports/finance/settings'],
        'intern' => ['label' => 'Intern', 'color' => '#10B981', 'desc' => 'Own department(s): same access as Staff'],
        'finance' => ['label' => 'Finance', 'color' => '#8B5CF6', 'desc' => 'Finance module only: ledger and vendor payments'],
        'hr' => ['label' => 'HR', 'full' => 'Human Resources', 'color' => '#7C3AED', 'desc' => 'HR management: staff records, onboarding, payroll'],
    ],

    'company_email_domain' => 'kretiv.co',

    // HR: fixed allowance types offered in a staff package (amounts set per person).
    // Payroll. Rates as at 2026; each statutory amount is pre-filled from
    // these and can be adjusted by HR on a draft run before it's finalised.
    'payroll' => [
        'pay_day' => 25,
        'ot_cutoff_day' => 15,          // OT from the 16th of last month to the 15th
        'ot_divisor_days' => 26,        // Employment Act: ordinary daily rate = monthly / 26
        'ot_daily_hours' => 8,
        'epf' => ['employee' => 0.11, 'employer_low' => 0.13, 'employer_high' => 0.12, 'high_from' => 5000, 'over60_employer' => 0.04],
        'socso' => ['ceiling' => 6000, 'employee' => 0.005, 'employer' => 0.0175, 'over60_employer' => 0.0125],
        'eis' => ['ceiling' => 6000, 'employee' => 0.002, 'employer' => 0.002],
    ],

    // Leave, Employment Act minimums. `by_service` is [years of service
    // from => days]; annual leave is pro-rated in the year someone joins.
    'leave' => [
        'types' => [
            'annual' => ['label' => 'Annual leave', 'by_service' => [0 => 8, 2 => 12, 5 => 16]],
            'sick' => ['label' => 'Sick leave', 'by_service' => [0 => 14, 2 => 18, 5 => 22], 'attachment' => true],
            'hospitalisation' => ['label' => 'Hospitalisation', 'days' => 60, 'attachment' => true],
            'maternity' => ['label' => 'Maternity leave', 'days' => 98],
            'paternity' => ['label' => 'Paternity leave', 'days' => 7],
            'unpaid' => ['label' => 'Unpaid leave'],
        ],
        'carry_forward_max' => 5,          // unused annual leave into next year
        'carry_forward_until' => '06-30',  // carried days must be used by this date
    ],

    'allowance_types' => ['transport' => 'Transport', 'phone' => 'Phone', 'meal' => 'Meal', 'other' => 'Other'],

    // Flexible hours: clock in from `earliest` (earlier counts from then),
    // late after `latest`; the day ends `day_hours` after clock in (incl. a
    // 1-hour break). Weekends are rest days. OT counts in `ot_block_minutes`
    // blocks, rated by day type, capped at `ot_monthly_cap_hours`.
    'attendance' => [
        'earliest' => '08:00',
        'latest' => '09:30',
        'day_hours' => 9,
        'rest_days' => [6, 0], // Saturday, Sunday (Carbon dayOfWeek)
        'ot_block_minutes' => 30,
        'ot_rates' => ['normal' => 1.5, 'rest' => 2.0, 'holiday' => 3.0],
        'ot_monthly_cap_hours' => 104,
    ],

    // Employment Act overtime applies to employees earning up to this monthly wage.
    'ot_wage_limit' => 4000,

    'job_types' => [
        'client_project' => ['label' => 'Client Project', 'color' => '#3A86FF', 'desc' => 'Kretivco does custom work for the customer'],
        'product_sale' => ['label' => 'Product Sale', 'color' => '#10B981', 'desc' => 'Customer buys an existing Kretivco product'],
    ],

    // Pre-packaged products staff can pick directly instead of typing a
    // Product Sale job's items by hand. A product line (e.g. Undangan.my)
    // prices differently depending on who's buying: an end user paying
    // Kretivco directly gets the full bundle (card + banner + signage); a
    // vendor like a wedding planner reselling Kretivco as their printer
    // gets a cheaper, card-only rate. The chosen tier's price and item
    // list get baked into the job's line_items at creation (see
    // JobController::resolvePackageTier()).
    'package_catalog' => [
        'print' => [
            [
                'key' => 'undangan_my',
                'label' => 'Undangan.my',
                'segments' => [
                    [
                        'key' => 'end_user',
                        'label' => 'End User (Direct Customer)',
                        'packages' => [
                            [
                                'key' => 'vip',
                                'label' => 'Undangan.my: VIP Wedding Card Package',
                                'items' => [
                                    '1x Undangan.my Digital Wedding Card',
                                    '4x8in Wedding Card Postcard with Envelope',
                                    '1x Banner 3x6ft',
                                    '4x Arrow 2x2ft',
                                    '2x Bunting 2x5ft',
                                ],
                                'tiers' => [
                                    ['pcs' => 100, 'price' => 380],
                                    ['pcs' => 200, 'price' => 410],
                                    ['pcs' => 300, 'price' => 448],
                                    ['pcs' => 400, 'price' => 468],
                                    ['pcs' => 500, 'price' => 485],
                                ],
                            ],
                        ],
                    ],
                    [
                        'key' => 'vendor',
                        'label' => 'Vendor (Wedding Planner)',
                        'packages' => [
                            [
                                'key' => 'dloveweddingplanner',
                                'label' => 'Undangan.my: DLoveWeddingPlanner Package',
                                'items' => [
                                    'Digital Card',
                                    'Physical Card {pcs}pcs',
                                    'Welcome Board',
                                ],
                                'tiers' => [
                                    ['pcs' => 100, 'price' => 197],
                                    ['pcs' => 200, 'price' => 274],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],

    'banks' => [
        'mbb' => ['label' => 'Maybank', 'code' => 'MBB', 'color' => '#FFC107'],
        'affin' => ['label' => 'AFFIN', 'code' => 'AFFIN', 'color' => '#E53935'],
    ],

    // One flow for every department: New (enquiry, nobody on it) ->
    // Quotation (taken in, quote sent; key "potential") -> Confirmed
    // (customer said yes, deposit / PO) -> In Progress (the work) ->
    // Delivered (goods delivered / work handed over) -> Completed (paid
    // and closed). Approvals (artwork, UAT, run sheet) sit on top of this
    // as a marker, not a stage. job_status_labels renames a stage for a
    // department.
    // Each status carries an icon alongside its color so a status badge
    // never depends on color alone to be told apart — Potential (indigo)
    // and In Progress (blue) sit close enough in hue that color-only
    // badges were hard to distinguish at a glance.
    'job_statuses' => [
        'new' => ['label' => 'New', 'color' => '#F59E0B', 'icon' => '✦'],
        'potential' => ['label' => 'Quotation', 'color' => '#6366F1', 'icon' => '🎯'],
        'confirmed' => ['label' => 'Confirmed', 'color' => '#0EA5E9', 'icon' => '✔'],
        'in_progress' => ['label' => 'In Progress', 'color' => '#3A86FF', 'icon' => '⚡'],
        'delivered' => ['label' => 'Delivered', 'color' => '#14B8A6', 'icon' => '📦'],
        'completed' => ['label' => 'Completed', 'color' => '#6B7280', 'icon' => '✓'],
        'cancelled' => ['label' => 'Cancelled', 'color' => '#EF4444', 'icon' => '🚫'],
    ],

    'job_status_labels' => [
        'print' => ['in_progress' => 'In Production', 'delivered' => 'Delivered'],
        'tech' => ['in_progress' => 'Development', 'delivered' => 'Handed Over'],
        'brand' => ['in_progress' => 'Design', 'delivered' => 'Handed Over'],
        'event' => ['in_progress' => 'Preparation', 'delivered' => 'Event Done'],
    ],

    // Orthogonal to job_statuses above — a job can be "In Progress" and
    // "Pending" at the same time (e.g. work technically ongoing but
    // waiting on customer confirmation). Visible flag + reason only, no
    // automatic SLA timer.
    'hold_statuses' => [
        'pending' => ['label' => 'Pending', 'color' => '#F59E0B', 'icon' => '⏸'],
        'suspended' => ['label' => 'Suspended', 'color' => '#EF4444', 'icon' => '⛔'],
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

    'brand' => [
        'name' => 'Kretivco Mediaworks',
        'ssm' => '(SA0463354-A)',
        'address_line_1' => 'No.15A, Jalan USJ1/19',
        'address_line_2' => '47600, Subang Jaya, Selangor',
        'email' => 'sales@kretiv.co',
        'phone' => '+6011-21149204',
        'phone2' => '+6019-3663805',
        // LHDN employer number (No. Majikan E), printed on Borang EA.
        'lhdn_employer_no' => env('LHDN_EMPLOYER_NO', ''),
    ],

    'bank_details' => [
        'mbb' => ['label' => 'MAYBANK', 'acct' => '5621-0668-8317', 'name' => 'KRETIVCO MEDIAWORKS'],
        'affin' => ['label' => 'AFFIN', 'acct' => '105630012033', 'name' => 'KRETIVCO MEDIAWORKS'],
    ],

    // Picking a department attributes the cost to that department (cost of
    // service); leaving it blank posts as a company-wide operating expense.
    // Capital allowance for the asset register (Malaysia, indicative default
    // rates: initial allowance % in the year of purchase, then annual
    // allowance % of cost each year until fully claimed). A tax agent should
    // confirm the category and rate for each asset.
    'capital_allowance' => [
        'small_value_limit' => 2000, // assets costing this much or less: 100% in the year of purchase
        'categories' => [
            'computer' => ['label' => 'Computer & IT equipment', 'ia' => 20, 'aa' => 20],
            'machinery' => ['label' => 'Machinery & tools (printer, camera, equipment)', 'ia' => 20, 'aa' => 14],
            'office' => ['label' => 'Office equipment', 'ia' => 20, 'aa' => 10],
            'furniture' => ['label' => 'Furniture & fittings', 'ia' => 20, 'aa' => 10],
            'vehicle' => ['label' => 'Motor vehicle', 'ia' => 20, 'aa' => 20],
        ],
    ],

    'expense_categories' => [
        'subcontractor' => 'Subcontractor / Consignment',
        'rent' => 'Rent',
        'utilities' => 'Utilities',
        'salary' => 'Salary',
        'commission' => 'Commission',
        'software' => 'Software & Subscriptions',
        'staff_claim' => 'Staff Claims',
        'entertainment' => 'Entertainment (client)',
        'other' => 'Other',
    ],

];
