<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\Approval;
use App\Models\Attendance;
use App\Models\Claim;
use App\Models\Customer;
use App\Models\Department;
use App\Models\ItemLibrary;
use App\Models\Job;
use App\Models\JobDocument;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use App\Models\PublicHoliday;
use App\Models\RecurringExpense;
use App\Models\User;
use App\Models\Vendor;
use App\Services\LeaveService;
use App\Services\LedgerService;
use App\Services\PayrollService;
use App\Support\DocumentData;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The demo company, Mirul Enterprise: staff, customers, jobs at every stage
 * with their documents and payments, a slow payer, an expired quotation, a
 * two-department project, artwork waiting for approval, attendance, leave,
 * claims, memos and last month's payroll. Everything is dated relative to
 * today, so the demo always looks current after the nightly reset
 * (php artisan demo:reset). All names, numbers and documents are made up.
 */
class DemoSeeder extends Seeder
{
    private LedgerService $ledger;

    /** @var array<string, User> */
    private array $u = [];

    /** @var array<string, Customer> */
    private array $c = [];

    /** @var array<string, Vendor> */
    private array $v = [];

    private Carbon $today;

    public function run(LedgerService $ledger, PayrollService $payroll): void
    {
        $this->ledger = $ledger;
        $this->today = Carbon::today();
        mt_srand(2026);

        $this->people();
        $this->departments();
        $this->customersAndVendors();
        $this->money();
        $this->jobs();
        $this->hr($payroll);

        Carbon::setTestNow();
    }

    private function at(int $daysAgo, string $time = '10:00'): Carbon
    {
        return $this->today->copy()->subDays($daysAgo)->setTimeFromTimeString($time);
    }

    // ---------------------------------------------------------------- people

    private function people(): void
    {
        $password = Hash::make(config('demo.password'));
        $staff = [
            // key => [name, email, role, department, title, staff no, salary, started, employment]
            'boss' => ['Mirul Hakimi', 'boss@demo.kretiv.co', User::ROLE_BOD, null, 'Managing Director', 'ME001', 9000, '2019-03-01', 'permanent'],
            'finance' => ['Siti Aisyah', 'finance@demo.kretiv.co', User::ROLE_FINANCE, 'admin', 'Account Executive', 'ME002', 3800, '2021-06-14', 'permanent'],
            'hr' => ['Farah Nadia', 'hr@demo.kretiv.co', User::ROLE_HR, 'admin', 'HR Executive', 'ME003', 3600, '2022-01-10', 'permanent'],
            'staff' => ['Aina Sofea', 'staff@demo.kretiv.co', User::ROLE_STAFF, 'print', 'Graphic Designer', 'ME004', 3200, '2023-02-01', 'permanent'],
            'head' => ['Daniel Lee', 'daniel@demo.kretiv.co', User::ROLE_DEPT_HEAD, 'print', 'Head of Signage & Print', 'ME005', 5200, '2020-08-03', 'permanent'],
            'hakim' => ['Hakim Rahman', 'hakim@demo.kretiv.co', User::ROLE_STAFF, 'event', 'Event Executive', 'ME006', 2800, '2024-04-15', 'permanent'],
            'kavitha' => ['Kavitha Raj', 'kavitha@demo.kretiv.co', User::ROLE_STAFF, 'brand', 'Social Media Executive', 'ME007', 3000, '2023-09-18', 'contract'],
            'irfan' => ['Irfan Hakim', 'irfan@demo.kretiv.co', User::ROLE_INTERN, 'tech', 'Web Developer (Intern)', 'ME008', 1200, $this->today->copy()->subMonths(5)->toDateString(), 'intern'],
        ];

        $n = 0;
        foreach ($staff as $key => [$name, $email, $role, $dept, $title, $no, $salary, $started, $type]) {
            $n++;
            $user = User::create([
                'name' => $name, 'short_name' => Str::before($name, ' '), 'email' => $email, 'password' => $password,
                'role' => $role, 'department' => $dept, 'visible_departments' => $key === 'head' ? ['print', 'brand'] : ($dept && $dept !== 'admin' ? [$dept] : null),
                'title' => $title, 'staff_id' => $no, 'active' => true, 'email_verified_at' => now(), 'must_change_password' => false,
            ]);
            $user->employee()->create([
                'staff_no' => $no, 'ic_number' => sprintf('000000-00-%04d', $n), 'date_of_birth' => '199'.($n % 9).'-0'.(($n % 9) + 1).'-1'.$n,
                'gender' => in_array($key, ['finance', 'hr', 'staff', 'kavitha'], true) ? 'female' : 'male',
                'phone' => sprintf('012-000 %04d', $n), 'personal_email' => Str::before($email, '@').'@personal.demo',
                'address' => 'No. '.$n.', Jalan Demo '.$n.'/2, 40000 Shah Alam, Selangor',
                'bank_name' => $n % 2 ? 'Maybank' : 'CIMB', 'bank_account' => sprintf('DEMO-%04d', 1000 + $n),
                'epf_number' => sprintf('DEMO%05d', $n), 'socso_number' => sprintf('DEMO%05d', $n), 'tax_number' => sprintf('SG DEMO%04d', $n),
                'emergency_name' => 'Waris '.Str::before($name, ' '), 'emergency_relation' => 'Family', 'emergency_phone' => sprintf('013-000 %04d', $n),
                'employment_type' => $type, 'start_date' => $started, 'basic_salary' => $salary, 'ot_eligible' => $salary < 4000,
                'end_date' => $key === 'irfan' ? $this->today->copy()->addDays(20)->toDateString() : null,
            ]);
            $this->u[$key] = $user;
        }

        foreach (['finance', 'hr', 'head'] as $k) {
            $this->u[$k]->employee->update(['reports_to_user_id' => $this->u['boss']->id]);
        }
        foreach (['staff', 'irfan'] as $k) {
            $this->u[$k]->employee->update(['reports_to_user_id' => $this->u['head']->id]);
        }
        foreach (['hakim', 'kavitha'] as $k) {
            $this->u[$k]->employee->update(['reports_to_user_id' => $this->u['boss']->id]);
        }
    }

    private function departments(): void
    {
        $profiles = [
            'print' => ['head', ['Signboards & banners', 'Cards, flyers & brochures', 'Stickers & labels']],
            'brand' => ['kavitha', ['Logo & brand identity', 'Social media', 'Menu & packaging design']],
            'tech' => ['irfan', ['Company websites', 'Online booking systems']],
            'event' => ['hakim', ['Corporate dinners', 'Launches & openings', 'PA system rental']],
            'admin' => ['finance', ['Accounts', 'HR']],
        ];
        foreach ($profiles as $key => [$head, $services]) {
            Department::where('key', $key)->first()?->update(['head_user_id' => $this->u[$head]->id, 'head_interim' => false, 'services' => $services, 'products' => []]);
        }
    }

    // ------------------------------------------------- customers and vendors

    private function customersAndVendors(): void
    {
        $customers = [
            'lim' => ['Encik Lim Wei Jie', 'Lim Renovation', 'company'],
            'maju' => ['Puan Rosnah', 'Syarikat Maju Jaya', 'company'],
            'makjah' => ['Mak Jah', 'Kedai Kek Mak Jah', 'company'],
            'tadika' => ['Cikgu Salmah', 'Tadika Ceria', 'company'],
            'ahseng' => ['Ah Seng', 'Bengkel Ah Seng', 'company'],
            'kafe' => ['Razak Ismail', 'Kafe Kopi Kampung', 'company'],
            'klinik' => ['Dr. Nurul Huda', 'Klinik Sihat', 'company'],
            'aida' => ['Puan Aida', null, 'individual'],
        ];
        $n = 0;
        foreach ($customers as $key => [$name, $company, $type]) {
            $n++;
            $this->c[$key] = Customer::create([
                'customer_id' => sprintf('KCO-%03d', $n), 'name' => $name, 'company' => $company, 'customer_type' => $type,
                'phone' => sprintf('011-000 %04d', $n), 'email' => Str::slug(Str::before($name, ' ')).$n.'@example.com',
                'source' => ['referral', 'walk-in', 'social_media', 'website'][$n % 4],
                'address_line_1' => "Lot {$n}, Jalan Contoh", 'postcode' => '40100', 'city' => 'Shah Alam', 'state' => 'Selangor',
                'created_by' => $this->u['boss']->id,
            ]);
        }

        $vendors = [
            'jaya' => ['Percetakan Jaya', 'printing'],
            'lalamove' => ['Lalamove', 'delivery'],
            'kayu' => ['Kedai Kayu Ah Chong', array_key_first(config('kretivco.vendor_categories'))],
            'sound' => ['Sound & Light Pro', array_key_first(config('kretivco.vendor_categories'))],
        ];
        $n = 0;
        foreach ($vendors as $key => [$name, $category]) {
            $n++;
            $this->v[$key] = Vendor::create(['vendor_id' => sprintf('KVE-%03d', $n), 'name' => $name, 'category' => array_key_exists($category, config('kretivco.vendor_categories')) ? $category : array_key_first(config('kretivco.vendor_categories')), 'phone' => sprintf('016-000 %04d', $n), 'created_by' => $this->u['boss']->id]);
        }

        foreach ([
            ['print', 'Bunting 2ft x 5ft', 'Full colour, with stand', 45], ['print', 'Backdrop 8ft x 8ft', 'Tension fabric, frame included', 680],
            ['print', 'Signboard 3D LED', 'Per foot, installation extra', 400], ['print', 'Sticker cutting', 'Per piece, up to A3', 35],
            ['brand', 'Logo design (3 concepts)', '2 rounds of revision', 1200], ['brand', 'Social media package (12 posts)', 'Design and captions', 1800],
            ['tech', 'Company website (5 pages)', 'Mobile friendly, 1 year hosting', 3800], ['event', 'PA system rental', 'Per day, with technician', 1200],
        ] as [$dept, $name, $desc, $price]) {
            ItemLibrary::create(['department' => $dept, 'item_name' => $name, 'description' => $desc, 'price' => $price, 'usage_count' => mt_rand(2, 30), 'active' => true, 'created_by' => $this->u['boss']->id]);
        }
    }

    // ------------------------------------------------------------ the books

    private function money(): void
    {
        Carbon::setTestNow($this->at(120));
        $this->ledger->postOpeningBalanceAdjustment('mbb', 45000, $this->u['finance']->name);
        $this->ledger->postOpeningBalanceAdjustment('affin', 12000, $this->u['finance']->name);

        for ($m = 3; $m >= 1; $m--) {
            $month = $this->today->copy()->startOfMonth()->subMonthsNoOverflow($m);
            foreach ([['rent', 2500, 1, 'Office rent'], ['utilities', 380, 7, 'Electricity and water'], ['utilities', 159, 5, 'Unifi business']] as [$category, $amount, $day, $notes]) {
                $date = $month->copy()->day($day);
                $this->ledger->postExpenseEntry(['amount' => $amount, 'bank' => 'mbb', 'category' => array_key_exists($category, config('kretivco.expense_categories')) ? $category : array_key_first(config('kretivco.expense_categories')), 'notes' => $notes, 'date' => $date], $this->u['finance']->name);
            }
        }

        foreach ([['Office rent', 'rent', 2500, 1], ['Unifi business', 'utilities', 159, 5], ['Adobe Creative Cloud', 'software', 275, 12]] as [$name, $category, $amount, $day]) {
            RecurringExpense::create([
                'name' => $name, 'category' => array_key_exists($category, config('kretivco.expense_categories')) ? $category : array_key_first(config('kretivco.expense_categories')),
                'amount' => $amount, 'bank' => 'mbb', 'day_of_month' => $day, 'active' => true,
                'last_recorded_on' => $this->today->copy()->startOfMonth()->subMonthNoOverflow()->day($day)->toDateString(),
            ]);
        }
        Carbon::setTestNow();
    }

    // ---------------------------------------------------------------- jobs

    private function jobs(): void
    {
        $lim = fn ($i, $q, $p, $d = '') => ['item' => $i, 'desc' => $d, 'size' => '', 'qty' => $q, 'price' => $p];

        // Lim: signboard quoted 3 days ago, artwork waiting for the customer.
        $a = $this->job('KP-2026-001', 'print', 'lim', 'Shop signboard', Job::STATUS_POTENTIAL, 'head', 3, [
            $lim('Signboard 3D LED 12ft x 3ft', 1, 4800, "* Acrylic letters with LED\n* Aluminium composite base"), $lim('Installation and wiring', 1, 650),
        ]);
        $this->issue($a, 'quotation', $this->at(3, '11:20'));
        $this->artwork($a, 'artwork-signboard.png', 'Signboard 3D LED 12ft x 3ft', 'staff', 1, null);

        // Syarikat Maju Jaya: the slow payer. Delivered, invoice overdue.
        $b = $this->job('KP-2026-002', 'print', 'maju', 'Exhibition banners', Job::STATUS_DELIVERED, 'staff', 50, [
            $lim('Bunting 2ft x 5ft', 10, 45), $lim('Backdrop 8ft x 8ft', 1, 680),
        ]);
        $this->issue($b, 'quotation', $this->at(50));
        $this->issue($b, 'delivery', $this->at(39));
        $this->issue($b, 'invoice', $this->at(38));
        $this->vendorCost($b, 'jaya', 520, 510);
        $h = $this->job('KB-2026-003', 'brand', 'maju', 'Company profile', Job::STATUS_COMPLETED, 'kavitha', 110, [$lim('Company profile, 16 pages', 1, 2400)]);
        $this->issue($h, 'quotation', $this->at(110));
        $this->issue($h, 'invoice', $this->at(95));
        $this->issue($h, 'receipt', $this->at(52), ['amount_paid' => 2400, 'payment_method' => 'Bank Transfer']);
        $j = $this->job('KT-2026-002', 'tech', 'maju', 'Online booking system', Job::STATUS_COMPLETED, 'irfan', 100, [$lim('Booking system with admin panel', 1, 6500)]);
        $this->issue($j, 'quotation', $this->at(100));
        $this->issue($j, 'invoice', $this->at(80));
        $this->issue($j, 'receipt', $this->at(35), ['amount_paid' => 6500, 'payment_method' => 'Online Banking']);

        // Mak Jah: in production, half paid.
        $c = $this->job('KP-2026-003', 'print', 'makjah', 'Printed cake boxes', Job::STATUS_IN_PROGRESS, 'staff', 12, [
            $lim('Cake box 8" printed', 500, 3.2), $lim('Label sticker', 500, 0.6),
        ]);
        $this->issue($c, 'quotation', $this->at(12));
        $this->issue($c, 'receipt', $this->at(9), ['amount_paid' => 950, 'payment_method' => 'Bank Transfer']);
        $this->vendorCost($c, 'jaya', 900, null);

        // Tadika Ceria: done and paid on time.
        $d = $this->job('KP-2026-004', 'print', 'tadika', 'Activity books', Job::STATUS_COMPLETED, 'head', 70, [$lim('Activity book A4, 32 pages', 200, 12.5)]);
        $this->issue($d, 'quotation', $this->at(70));
        $this->issue($d, 'delivery', $this->at(56));
        $this->issue($d, 'invoice', $this->at(55));
        $this->issue($d, 'receipt', $this->at(50), ['amount_paid' => 2500, 'payment_method' => 'Bank Transfer']);

        // Bengkel Ah Seng: new today, nobody has taken it in yet.
        $this->job('KP-2026-005', 'print', 'ahseng', 'Car stickers', Job::STATUS_NEW, null, 0, [$lim('Sticker cutting', 20, 35)]);

        // Kafe Kopi Kampung: confirmed, deposit in, logo approved.
        $f = $this->job('KB-2026-001', 'brand', 'kafe', 'Logo and menu', Job::STATUS_CONFIRMED, 'kavitha', 8, [
            $lim('Logo design (3 concepts)', 1, 1200), $lim('Menu design A3, 2 sides', 1, 450),
        ]);
        $this->issue($f, 'quotation', $this->at(8));
        $this->issue($f, 'receipt', $this->at(6), ['amount_paid' => 825, 'payment_method' => 'Online Banking']);
        $this->artwork($f, 'artwork-logo.png', 'Logo design (3 concepts)', 'kavitha', 2, 'Razak Ismail');

        // Klinik Sihat: a quotation that expired with no answer, and a website underway.
        $g = $this->job('KB-2026-002', 'brand', 'klinik', 'Social media for October', Job::STATUS_POTENTIAL, 'kavitha', 20, [$lim('Social media package (12 posts)', 1, 1800)]);
        $this->issue($g, 'quotation', $this->at(20));
        $i = $this->job('KT-2026-001', 'tech', 'klinik', 'Clinic website', Job::STATUS_IN_PROGRESS, 'irfan', 25, [
            $lim('Company website (5 pages)', 1, 3800), $lim('Hosting and domain, 1 year', 1, 350),
        ]);
        $this->issue($i, 'quotation', $this->at(25));
        $this->issue($i, 'receipt', $this->at(21), ['amount_paid' => 2075, 'payment_method' => 'Bank Transfer']);

        // Puan Aida: dinner done, deposit paid, invoice for the balance due in 2 days.
        $k = $this->job('KE-2026-001', 'event', 'aida', 'Annual dinner', Job::STATUS_DELIVERED, 'hakim', 30, [
            $lim('PA system rental', 1, 1200), $lim('Stage and backdrop', 1, 2300), $lim('Emcee', 1, 800),
        ]);
        $this->issue($k, 'quotation', $this->at(30));
        $this->issue($k, 'receipt', $this->at(27), ['amount_paid' => 2150, 'payment_method' => 'Cash']);
        $this->issue($k, 'delivery', $this->at(13));
        $this->issue($k, 'invoice', $this->at(12));
        $this->vendorCost($k, 'sound', 700, 700);

        // Lim again: a launch event just quoted.
        $l = $this->job('KE-2026-002', 'event', 'lim', 'Branch opening', Job::STATUS_POTENTIAL, 'hakim', 2, [$lim('Event management', 1, 3500), $lim('Launch gimmick', 1, 900)]);
        $this->issue($l, 'quotation', $this->at(2));

        // Kafe Kopi Kampung's new branch: one project, two departments, one quotation.
        $m1 = $this->job('KP-2026-006', 'print', 'kafe', 'Menu board and banners', Job::STATUS_POTENTIAL, 'staff', 1, [$lim('Menu board', 2, 380), $lim('Banner 3ft x 6ft', 2, 120)], 'PRJ-2026-001');
        $m2 = $this->job('KB-2026-004', 'brand', 'kafe', 'Branch branding kit', Job::STATUS_POTENTIAL, 'kavitha', 1, [$lim('Branding kit (signage, cups, bags)', 1, 1500)], 'PRJ-2026-001');
        $this->projectQuotation(collect([$m1, $m2]), 'New branch: Kafe Kopi Kampung', $this->at(1, '15:40'));
    }

    /** @param array<int, array<string, mixed>> $items */
    private function job(string $code, string $dept, string $customer, string $title, string $status, ?string $pic, int $daysAgo, array $items, ?string $project = null): Job
    {
        $created = $this->at($daysAgo, '09:30');
        Carbon::setTestNow($created);
        $total = collect($items)->sum(fn ($i) => $i['qty'] * $i['price']);
        $job = Job::create([
            'job_id' => $code, 'customer_id' => $this->c[$customer]->id, 'department' => $dept, 'job_type' => $title, 'job_type_category' => 'client_project',
            'status' => $status, 'estimation_value' => $total, 'final_value' => $status === Job::STATUS_COMPLETED ? $total : null, 'line_items' => $items,
            'pic' => $pic ? $this->u[$pic]->name : null, 'start_date' => $created->toDateString(), 'deadline' => $created->copy()->addDays(21)->toDateString(),
            'bank' => $dept === 'event' ? 'affin' : 'mbb', 'project_id' => $project, 'created_by' => $this->u['boss']->id, 'archived' => false,
        ]);
        $this->log($job, $this->u['boss']->name, 'created', 'Created for '.$this->c[$customer]->displayName());
        if ($status !== Job::STATUS_NEW) {
            ActivityLog::create(['job_id' => $job->id, 'job_code' => $job->job_id, 'user_name' => $job->pic ?? $this->u['boss']->name, 'action' => 'status_change', 'field_changed' => 'status', 'old_value' => Job::STATUS_NEW, 'new_value' => $status]);
        }
        Carbon::setTestNow();

        return $job;
    }

    /** Issues a document the way the Documents card does: numbered PDF, ledger entry, history. */
    private function issue(Job $job, string $type, Carbon $at, array $input = []): void
    {
        Carbon::setTestNow($at);
        $job->refresh();
        [$basis, $invoiceNumber, $paid] = DocumentData::projectBasis(collect([$job]));
        if ($type !== 'receipt') {
            $basis = null;
            $invoiceNumber = null;
            $paid = $type === 'invoice' ? $paid : 0.0;
        }
        $number = DocumentData::number($type, $job);
        $by = $type === 'receipt' || $type === 'invoice' ? $this->u['finance'] : ($job->pic ? User::where('name', $job->pic)->first() : $this->u['boss']);
        $doc = DocumentData::build($job, $type, $input + ['title' => $job->job_type], $number, $by->short_name ?? $by->name, $basis, $invoiceNumber, $paid);

        if ($type === 'invoice') {
            $this->ledger->postInvoiceEntry($job, $number, $by->name, $doc['total']);
        }
        if ($type === 'receipt') {
            $this->ledger->postReceiptEntry($job, $number, $by->name, $doc['amount_paid'], $job->bank);
        }
        $this->store($job, $type, $number, Pdf::loadView('documents.pdf', ['doc' => $doc])->output(), $by, $at);
        Carbon::setTestNow();
    }

    private function projectQuotation(Collection $jobs, string $title, Carbon $at): void
    {
        Carbon::setTestNow($at);
        $number = DocumentData::projectNumber('quotation', $jobs);
        $doc = DocumentData::buildProject($jobs->first(), $jobs, 'quotation', ['title' => $title], $number, $this->u['staff']->short_name);
        $bytes = Pdf::loadView('documents.pdf', ['doc' => $doc])->output();
        foreach ($jobs as $job) {
            $this->store($job, 'quotation', $number, $bytes, $this->u['staff'], $at);
        }
        Carbon::setTestNow();
    }

    private function store(Job $job, string $type, string $number, string $bytes, User $by, Carbon $at): void
    {
        $path = "{$job->job_id}/document/{$at->timestamp}_{$number}.pdf";
        Storage::disk('public')->put($path, $bytes);
        JobDocument::create(['job_id' => $job->id, 'doc_type' => $type, 'doc_number' => $number, 'storage_path' => $path, 'filename' => "{$number}.pdf", 'generated_by' => $by->id, 'generated_at' => $at]);
        $label = DocumentData::label($type, $job->department);
        $this->log($job, $by->name, 'document_generated', 'generated '.(in_array(strtolower($label[0]), ['a', 'e', 'i', 'o', 'u'], true) ? 'an ' : 'a ').$label." ({$number})");
    }

    /** Artwork on the job, sent to the customer; approved when a customer name is given. */
    private function artwork(Job $job, string $file, string $item, string $by, int $sentDaysAgo, ?string $approvedBy): void
    {
        $at = $this->at($sentDaysAgo, '14:10');
        Carbon::setTestNow($at);
        $path = "{$job->job_id}/artwork/0/{$at->timestamp}_{$file}";
        Storage::disk('public')->put($path, file_get_contents(database_path("seeders/demo/{$file}")));
        $id = (string) Str::uuid();
        $job->update(['attachments' => [...($job->attachments ?? []), [
            'id' => $id, 'kind' => 'artwork', 'line_item_id' => '0', 'design' => 1, 'path' => $path, 'name' => $file,
            'uploaded_by' => $this->u[$by]->name, 'uploaded_at' => $at->toIso8601String(),
        ]]]);
        Approval::create([
            'job_id' => $job->id, 'token' => (string) Str::uuid(), 'line_item_id' => '0', 'design' => 1, 'version' => 1, 'item_name' => $item,
            'details' => 'Please check the spelling, colours and size.', 'attachment_ids' => [$id], 'sent_by' => $this->u[$by]->short_name,
            'status' => $approvedBy ? 'approved' : 'sent', 'customer_name' => $approvedBy,
            'responded_at' => $approvedBy ? $at->copy()->addHours(3) : null, 'ip' => $approvedBy ? '127.0.0.1' : null, 'user_agent' => $approvedBy ? 'Demo' : null,
        ]);
        $this->log($job, $this->u[$by]->name, 'edited', "sent {$item}, design 1 v1 for customer approval");
        if ($approvedBy) {
            Carbon::setTestNow($at->copy()->addHours(3));
            $this->log($job, "{$approvedBy} (customer)", 'edited', "approved {$item}, design 1 v1");
        }
        Carbon::setTestNow();
    }

    private function vendorCost(Job $job, string $vendor, float $estimate, ?float $actual): void
    {
        $job->update(['vendor_costs' => [...($job->vendor_costs ?? []), [
            'id' => (string) Str::uuid(), 'vendor_id' => $this->v[$vendor]->id, 'estimated_cost' => $estimate, 'actual_cost' => $actual, 'notes' => null, 'status' => 'unpaid',
        ]]]);
    }

    private function log(Job $job, string $who, string $action, string $detail): void
    {
        ActivityLog::create(['job_id' => $job->id, 'job_code' => $job->job_id, 'user_name' => $who, 'action' => $action, 'detail' => $detail]);
    }

    // ------------------------------------------------------------------ HR

    private function hr(PayrollService $payroll): void
    {
        // Attendance for the last three weeks of working days.
        $holidays = PublicHoliday::pluck('date')->map(fn ($d) => Carbon::parse($d)->toDateString())->all();
        $crew = ['boss', 'finance', 'hr', 'staff', 'head', 'hakim', 'kavitha', 'irfan'];
        for ($back = 21; $back >= 1; $back--) {
            $day = $this->today->copy()->subDays($back);
            if ($day->isWeekend() || in_array($day->toDateString(), $holidays, true)) {
                continue;
            }
            foreach ($crew as $k) {
                $in = $day->copy()->setTime(8, 30)->addMinutes(mt_rand(0, 55) + ($k === 'hakim' && $back % 5 === 0 ? 20 : 0));
                $ot = $k === 'hakim' && $back === 3 ? 120 : 0;
                $out = $in->copy()->addHours(9)->addMinutes(mt_rand(0, 35) + $ot);
                Attendance::create([
                    'user_id' => $this->u[$k]->id, 'date' => $day->toDateString(), 'clock_in' => $in, 'clock_out' => $out,
                    'work_mode' => $back % 7 === 0 && in_array($k, ['kavitha', 'irfan'], true) ? 'wfh' : 'wfo',
                    'late' => $in->format('H:i') > config('kretivco.attendance.latest'), 'day_type' => 'normal',
                    'ot_minutes' => $ot, 'ot_rate' => 1.5, 'ot_status' => $ot ? 'pending' : 'none',
                ]);
            }
        }

        // Leave: one waiting for approval, one approved, one sick day taken.
        $nextTuesday = $this->today->copy()->next(Carbon::TUESDAY);
        $leaves = [
            ['staff', 'annual', $nextTuesday, $nextTuesday, 'pending', 'Family matters'],
            ['kavitha', 'annual', $this->today->copy()->next(Carbon::THURSDAY)->addWeek(), $this->today->copy()->next(Carbon::FRIDAY)->addWeek(), 'approved', 'Balik kampung'],
            ['hakim', 'sick', $this->today->copy()->subDays(8), $this->today->copy()->subDays(8), 'approved', 'Fever'],
        ];
        foreach ($leaves as [$k, $type, $from, $to, $status, $reason]) {
            LeaveRequest::create([
                'user_id' => $this->u[$k]->id, 'type' => $type, 'start_date' => $from->toDateString(), 'end_date' => $to->toDateString(),
                'days' => LeaveService::workingDays($from, $to), 'reason' => $reason, 'status' => $status,
                'decided_by' => $status === 'approved' ? $this->u['boss']->id : null, 'decided_at' => $status === 'approved' ? now()->subDays(2) : null,
            ]);
        }

        // Claims at each step.
        foreach ([
            ['hakim', 'fuel', 86.40, 'Fuel to the venue, Puan Aida dinner', 'submitted', 'event'],
            ['staff', 'supplies', 45.90, 'Cutting mat and blades', 'approved', 'print'],
            ['kavitha', 'parking', 18.00, 'Parking at Kafe Kopi Kampung', 'pending', 'brand'],
        ] as [$k, $category, $amount, $desc, $status, $dept]) {
            Claim::create([
                'user_id' => $this->u[$k]->id, 'claimant_name' => $this->u[$k]->name, 'date' => $this->today->copy()->subDays(mt_rand(2, 9))->toDateString(),
                'category' => $category, 'department' => $dept, 'description' => $desc, 'amount' => $amount, 'status' => $status,
                'decided_by' => $status === 'approved' ? $this->u['boss']->id : null, 'decided_at' => $status === 'approved' ? now()->subDay() : null,
            ]);
        }

        // A memo everyone must acknowledge, and a pinned announcement.
        Carbon::setTestNow($this->at(2, '09:00'));
        Announcement::create(['type' => 'memo', 'ref_no' => Announcement::nextRef(), 'title' => 'Flexible working hours from next month', 'body' => "Starting next month, office hours are flexible: clock in between 8:00 and 9:30am and work 9 hours.\n\nPlease acknowledge this memo in KretivOS.", 'audience' => null, 'requires_ack' => true, 'pinned' => false, 'published_by' => $this->u['hr']->id]);
        Announcement::create(['type' => 'announcement', 'ref_no' => null, 'title' => 'Company dinner at the end of the month', 'body' => 'Our annual dinner is on the last Friday of the month at 8pm. See you there.', 'audience' => null, 'requires_ack' => false, 'pinned' => true, 'published_by' => $this->u['boss']->id]);
        Carbon::setTestNow();

        // Last month's payroll, finalised on pay day; statutory paid by the 12th if that's passed.
        $month = $this->today->copy()->startOfMonth()->subMonthNoOverflow();
        $payDay = $month->copy()->day((int) config('kretivco.payroll.pay_day'));
        $payDay = $payDay->isWeekend() ? $payDay->previousWeekday() : $payDay;
        Carbon::setTestNow($payDay->copy()->setTime(10, 0));
        $run = PayrollRun::create(['period' => $month->format('Y-m'), 'pay_date' => $payDay]);
        $payroll->prepare($run);
        $payroll->finalize($run->fresh(), 'mbb', $this->u['hr']);
        if ($this->today->day > 12) {
            Carbon::setTestNow($this->today->copy()->day(12)->setTime(11, 0));
            $payroll->payStatutory($run->fresh(), 'mbb', $this->u['finance']);
        }
        Carbon::setTestNow();
    }
}
