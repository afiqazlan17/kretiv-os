<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-white leading-tight">Collections</h2>
    </x-slot>

    <div class="p-5 md:p-7 space-y-4" x-data="collections()">
        <div class="grid grid-cols-2 gap-4 max-w-xl">
            <div class="k-card p-5">
                <div class="text-2xl font-extrabold text-gray-900">RM {{ number_format($totalOwed, 2) }}</div>
                <div class="text-sm font-semibold text-gray-600 mt-1">Owed by customers</div>
            </div>
            <div class="k-card p-5">
                <div class="text-2xl font-extrabold text-gray-900">{{ $rows->count() }}</div>
                <div class="text-sm font-semibold text-gray-600 mt-1">{{ \Illuminate\Support\Str::plural('invoice', $rows->count()) }} with a balance</div>
            </div>
        </div>

        <div class="k-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide border-b border-[#F0EDE9]">
                            <th class="px-4 py-3">Customer &amp; invoice</th>
                            <th class="px-4 py-3 text-right hidden sm:table-cell">Invoiced</th>
                            <th class="px-4 py-3 text-right">Balance</th>
                            <th class="px-4 py-3 hidden md:table-cell">Age</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#F0EDE9]">
                        @forelse ($rows as $i => $r)
                            <tr class="hover:bg-[#F7FDF9]">
                                <td class="px-4 py-3.5 max-w-0 w-full">
                                    <div class="font-semibold text-gray-800 truncate">{{ $r['job']->customer?->name ?? 'No customer' }}</div>
                                    <div class="text-xs text-gray-400 truncate">
                                        <span class="font-mono">{{ $r['invoice']->doc_number }}</span> ·
                                        <a href="{{ route('jobs.show', $r['job']) }}" target="_blank" class="hover:underline">{{ $r['job']->job_id }} {{ $r['job']->job_type }}</a>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5 text-right whitespace-nowrap text-gray-600 hidden sm:table-cell">RM {{ number_format($r['total'], 2) }}</td>
                                <td class="px-4 py-3.5 text-right whitespace-nowrap font-bold text-gray-900">RM {{ number_format($r['balance'], 2) }}</td>
                                <td class="px-4 py-3.5 whitespace-nowrap hidden md:table-cell">
                                    <span class="text-xs font-semibold rounded-full px-2.5 py-0.5 {{ $r['days'] > 30 ? 'bg-red-50 text-red-700' : ($r['days'] > 7 ? 'bg-amber-50 text-amber-700' : 'bg-gray-100 text-gray-600') }}">{{ $r['days'] }} {{ \Illuminate\Support\Str::plural('day', $r['days']) }}</span>
                                </td>
                                <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                    @if ($r['wa_phone'])
                                        <button type="button" @click="open(@js([
                                            'name' => $r['job']->customer?->name,
                                            'invoice' => $r['invoice']->doc_number,
                                            'job' => $r['job']->job_type,
                                            'total' => number_format($r['total'], 2),
                                            'balance' => number_format($r['balance'], 2),
                                            'bank' => $r['bank'] ? "{$r['bank']['label']} {$r['bank']['acct']} ({$r['bank']['name']})" : 'our bank account',
                                            'phone' => $r['wa_phone'],
                                        ]))" class="inline-flex items-center gap-1 text-xs font-semibold px-3 py-1.5 rounded-lg text-white bg-[#25D366] hover:brightness-105">
                                            <x-icon name="message-circle" class="w-3.5 h-3.5" /> Remind
                                        </button>
                                    @else
                                        <span class="text-xs text-gray-400">No phone</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-10 text-center text-gray-500">No outstanding invoices. Everyone has paid.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Reminder composer: the message is pre-filled and can be edited before it opens in WhatsApp. --}}
        <div x-show="show" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center bg-black/40 p-4" @keydown.escape.window="show = false">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-5" @click.outside="show = false">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-base font-bold text-gray-900">Payment reminder</h3>
                    <button type="button" @click="show = false" class="text-gray-400 hover:text-gray-700" aria-label="Close"><x-icon name="x" class="w-5 h-5" /></button>
                </div>
                <p class="text-xs text-gray-500 mb-2">Edit the message if needed, then send it on WhatsApp.</p>
                <textarea x-model="message" rows="9" class="w-full text-sm"></textarea>
                <div class="flex items-center justify-end gap-2 mt-3">
                    <button type="button" @click="show = false" class="text-sm font-semibold px-4 py-2 rounded-xl border border-[#EFE3DE] text-gray-700 hover:bg-gray-50">Cancel</button>
                    <a :href="`https://wa.me/${phone}?text=${encodeURIComponent(message)}`" target="_blank" rel="noopener" @click="show = false"
                       class="inline-flex items-center gap-1.5 text-sm font-semibold px-4 py-2 rounded-xl text-white bg-[#25D366] hover:brightness-105">
                        <x-icon name="message-circle" class="w-4 h-4" /> Send on WhatsApp
                    </a>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function collections() {
            return {
                show: false, phone: '', message: '',
                open(r) {
                    this.phone = r.phone;
                    this.message = `Salam ${r.name || ''},\n\nIni peringatan mesra daripada Kretivco Mediaworks. Invois ${r.invoice} untuk "${r.job}" (jumlah RM ${r.total}) masih ada baki RM ${r.balance}.\n\nMohon jelaskan bayaran ke ${r.bank}. Abaikan mesej ini jika bayaran telah dibuat.\n\nTerima kasih.`;
                    this.show = true;
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
