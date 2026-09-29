@php
    $officer = config('kretivco.privacy.officer');
    $email = config('kretivco.privacy.email');
    $company = config('kretivco.brand.name').' '.config('kretivco.brand.ssm');
    $updated = config('kretivco.privacy.updated');
    $notice = [
        'en' => [
            'title' => 'Staff Privacy Notice',
            'updated' => 'Last updated',
            'sections' => [
                ['What we collect', [
                    '<b>Identity and contact:</b> name, IC number, date of birth, gender, phone, personal email, home address and emergency contact.',
                    '<b>Employment:</b> staff number, job title, department, reporting line, employment type, and start and end dates.',
                    '<b>Pay and statutory:</b> basic salary, allowances, bank name and account number, EPF, SOCSO and income tax numbers, payslips and EA forms.',
                    '<b>Work records:</b> attendance (clock-in and clock-out times and work mode), overtime, leave requests and supporting documents (such as medical certificates), expense claims and receipts.',
                    '<b>System use:</b> your login, the actions you take in KretivOS (audit log), and the IP address and browser of your sessions.',
                ], 'We do <b>not</b> track your location, camera or microphone.'],
                ['Why we use it', 'To employ you and run the company: pay salaries and statutory contributions (EPF, SOCSO, EIS, PCB), issue payslips and EA forms, manage leave, attendance and claims, contact you or your emergency contact, keep the systems secure, and meet our legal duties.'],
                ['Is it required?', 'Identity, bank and statutory details are required to pay you and meet the law. Without them we cannot process your salary or contributions. Other details, such as a personal email, are optional.'],
                ['Who we share it with', 'Only as needed: KWSP, PERKESO and LHDN; our bank (for salary transfers); our accountant and auditor; and our hosting provider, who stores the data for us. We share data with authorities when the law requires it. <b>We never sell your data.</b>'],
                ['Who can see it inside Kretivco', 'Your salary, bank and IC details are visible only to HR and the Board of Directors. Every change is recorded in the audit log.'],
                ['How long we keep it', 'While you work with us, and up to 7 years after you leave, as required by employment, tax and company laws. After that it is deleted.'],
                ['Your rights', 'You can view your details on your Profile page and request corrections there. You may also ask for a copy of your data, or ask us to limit how we use it where the law allows.'],
                ['If something goes wrong', 'If a data breach affects you, we will tell you and report it to the Personal Data Protection Commissioner as the law requires.'],
            ],
            'contact' => 'Contact',
        ],
        'ms' => [
            'title' => 'Notis Privasi Staff',
            'updated' => 'Kemas kini terakhir',
            'sections' => [
                ['Data yang kami kumpul', [
                    '<b>Identiti dan hubungan:</b> nama, no. kad pengenalan, tarikh lahir, jantina, telefon, emel peribadi, alamat rumah dan waris untuk kecemasan.',
                    '<b>Pekerjaan:</b> no. staff, jawatan, jabatan, ketua, jenis pekerjaan, serta tarikh mula dan tamat.',
                    '<b>Gaji dan berkanun:</b> gaji pokok, elaun, nama bank dan no. akaun, no. KWSP, PERKESO dan cukai pendapatan, slip gaji dan Borang EA.',
                    '<b>Rekod kerja:</b> kehadiran (masa masuk dan keluar serta mod kerja), kerja lebih masa, permohonan cuti dan dokumen sokongan (seperti sijil cuti sakit), tuntutan dan resit.',
                    '<b>Penggunaan sistem:</b> log masuk anda, tindakan anda dalam KretivOS (log audit), dan alamat IP serta pelayar sesi anda.',
                ], 'Kami <b>tidak</b> menjejak lokasi, kamera atau mikrofon anda.'],
                ['Tujuan penggunaan', 'Untuk menggaji anda dan menjalankan syarikat: membayar gaji dan caruman berkanun (KWSP, PERKESO, SIP, PCB), mengeluarkan slip gaji dan Borang EA, mengurus cuti, kehadiran dan tuntutan, menghubungi anda atau waris anda, menjaga keselamatan sistem, dan mematuhi undang-undang.'],
                ['Adakah wajib?', 'Butiran identiti, bank dan berkanun adalah wajib untuk membayar anda dan mematuhi undang-undang. Tanpanya, kami tidak dapat memproses gaji atau caruman anda. Butiran lain, seperti emel peribadi, adalah pilihan.'],
                ['Siapa yang menerima data', 'Hanya bila perlu: KWSP, PERKESO dan LHDN; bank kami (untuk pindahan gaji); akauntan dan juruaudit kami; dan penyedia hosting yang menyimpan data bagi pihak kami. Kami berkongsi data dengan pihak berkuasa jika dikehendaki undang-undang. <b>Kami tidak sekali-kali menjual data anda.</b>'],
                ['Siapa boleh lihat dalam Kretivco', 'Butiran gaji, bank dan IC anda hanya boleh dilihat oleh HR dan Lembaga Pengarah. Setiap perubahan direkod dalam log audit.'],
                ['Tempoh simpanan', 'Sepanjang anda bekerja dengan kami, dan sehingga 7 tahun selepas anda berhenti, seperti yang dikehendaki undang-undang pekerjaan, cukai dan syarikat. Selepas itu data dipadam.'],
                ['Hak anda', 'Anda boleh melihat butiran anda di halaman Profile dan memohon pembetulan di situ. Anda juga boleh meminta salinan data anda, atau meminta kami menghadkan penggunaannya setakat yang dibenarkan undang-undang.'],
                ['Jika berlaku masalah', 'Jika berlaku kebocoran data yang melibatkan anda, kami akan memaklumkan anda dan melaporkannya kepada Pesuruhjaya Perlindungan Data Peribadi seperti yang dikehendaki undang-undang.'],
            ],
            'contact' => 'Hubungi',
        ],
    ];
@endphp
<x-os-layout>
    <div class="max-w-3xl mx-auto pt-4 space-y-4" x-data="{ lang: 'en' }">
        <a href="{{ route('os.home') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-white/70 hover:text-white"><x-icon name="chevron-left" class="w-4 h-4" /> Back to KretivOS</a>
        @if (session('success'))
            <div class="rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('success') }}</div>
        @endif

        <div class="bg-white rounded-2xl shadow-xl p-6 md:p-8 text-sm text-gray-700 leading-relaxed">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
                <div class="flex items-center gap-2.5">
                    <span class="w-10 h-10 rounded-xl bg-[#FFF0F5] text-[#C2185B] flex items-center justify-center"><x-icon name="shield-check" class="w-5 h-5" /></span>
                    <div>
                        <h1 class="text-lg font-bold text-gray-900" x-text="lang === 'en' ? @js($notice['en']['title']) : @js($notice['ms']['title'])"></h1>
                        <p class="text-xs text-gray-400">{{ $company }}</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-1 rounded-lg bg-[#F3EAE6] p-1 text-xs font-semibold">
                    <button type="button" @click="lang = 'en'" class="px-3 py-1.5 rounded-md" :class="lang === 'en' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500'">English</button>
                    <button type="button" @click="lang = 'ms'" class="px-3 py-1.5 rounded-md" :class="lang === 'ms' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500'">Bahasa Melayu</button>
                </div>
            </div>

            @foreach ($notice as $code => $n)
                <div x-show="lang === '{{ $code }}'" @if ($code !== 'en') x-cloak @endif class="space-y-5">
                    <p class="text-xs text-gray-400">{{ $n['updated'] }}: {{ $updated }}</p>
                    @foreach ($n['sections'] as $i => $section)
                        <section>
                            <h2 class="font-bold text-gray-900 mb-1.5">{{ $i + 1 }}. {{ $section[0] }}</h2>
                            @if (is_array($section[1]))
                                <ul class="list-disc pl-5 space-y-1">
                                    @foreach ($section[1] as $line)<li>{!! $line !!}</li>@endforeach
                                </ul>
                                <p class="mt-2">{!! $section[2] !!}</p>
                            @else
                                <p>{!! $section[1] !!}</p>
                            @endif
                            @if ($i === 6)
                                <p class="mt-2">{{ $n['contact'] }}: <b>{{ $officer }}</b>, <a href="mailto:{{ $email }}" class="text-[#C2185B] hover:underline">{{ $email }}</a></p>
                            @endif
                        </section>
                    @endforeach
                </div>
            @endforeach

            <div class="mt-6 pt-5 border-t border-[#F5ECE8]">
                @if ($mine)
                    <p class="flex items-center gap-2 text-[#047857]"><x-icon name="circle-check" class="w-4 h-4" /> You acknowledged this notice on {{ $mine->acknowledged_at->format('d M Y, g:ia') }}.</p>
                @else
                    <form method="POST" action="{{ route('privacy.acknowledge') }}" x-data="{ ok: false }" class="space-y-3">
                        @csrf
                        <label class="flex items-start gap-2.5 cursor-pointer">
                            <input type="checkbox" x-model="ok" class="mt-0.5 rounded text-[#C2185B]">
                            <span>I have read the Staff Privacy Notice.<span class="block text-xs text-gray-500">Saya telah membaca Notis Privasi Staff.</span></span>
                        </label>
                        <button type="submit" :disabled="!ok" class="text-sm font-bold px-5 py-2.5 rounded-xl text-white bg-gradient-to-r from-[#E91E63] to-[#F46A3A] hover:brightness-110 disabled:opacity-40">Acknowledge</button>
                    </form>
                @endif
            </div>
        </div>

        @if ($everyone)
            <div class="bg-white rounded-2xl shadow-xl p-6 text-sm">
                <h2 class="font-bold text-gray-900 mb-1">Acknowledgements</h2>
                <p class="text-xs text-gray-400 mb-3">Visible to HR and the Board. {{ $everyone->whereNotNull('at')->count() }} of {{ $everyone->count() }} active staff have acknowledged this version.</p>
                <div class="divide-y divide-[#F5ECE8]">
                    @foreach ($everyone as $row)
                        <div class="flex items-center justify-between py-2">
                            <span class="text-gray-700">{{ $row['name'] }}</span>
                            @if ($row['at'])
                                <span class="text-xs text-[#047857]">{{ \Illuminate\Support\Carbon::parse($row['at'])->format('d M Y, g:ia') }}</span>
                            @else
                                <span class="text-xs text-amber-600">Not yet</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-os-layout>
