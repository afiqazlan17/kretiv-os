{{-- Preview / edit modal for Quotation, Proforma, Invoice and Receipt. The right-hand
     preview is the real dompdf output (POST .../preview), so what you see is what
     Download PDF produces. --}}
<div x-data="documentModal(@js([
        'jobCode' => $job->job_id,
        'urls' => collect(['draft', 'preview', 'save', 'generate'])->mapWithKeys(fn ($a) => [$a => route('jobs.documents.'.$a, [$job, '__TYPE__'])])->all(),
    ]))"
     @open-document.window="openFor($event.detail.type)"
     @keydown.escape.window="open && close()"
     x-show="open" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-2 sm:p-6">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-6xl h-[92vh] flex flex-col overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
            <h2 class="text-base font-bold text-gray-900" x-text="`Preview ${label} — ${jobCode}`"></h2>
            <button type="button" @click="close()" class="text-gray-400 hover:text-gray-700 text-xl leading-none">×</button>
        </div>

        {{-- On phones, the form and the live PDF preview used to be squeezed into one
             scrolling column with no fixed height, which made typing feel like it was
             fighting the preview for space. A tab switch lets you fully hide one side. --}}
        <div class="lg:hidden flex gap-2 px-5 pt-3">
            <button type="button" @click="mobileTab = 'form'" class="flex-1 text-xs font-semibold px-3 py-1.5 rounded-md border"
                    :class="mobileTab === 'form' ? 'bg-gray-800 text-white border-gray-800' : 'border-gray-200 text-gray-600'">Form</button>
            <button type="button" @click="mobileTab = 'preview'" class="flex-1 text-xs font-semibold px-3 py-1.5 rounded-md border"
                    :class="mobileTab === 'preview' ? 'bg-gray-800 text-white border-gray-800' : 'border-gray-200 text-gray-600'">Preview</button>
        </div>
        <div class="flex-1 min-h-0 grid grid-cols-1 auto-rows-fr lg:grid-cols-[minmax(0,380px)_minmax(0,1fr)] lg:auto-rows-auto">
            {{-- Form --}}
            <div class="overflow-y-auto p-5 space-y-3 border-r border-gray-100 text-sm" :class="mobileTab === 'preview' ? 'hidden lg:block' : ''" @input="schedule()" @change="schedule()" @keyup="schedule()">
                <p x-show="loading" class="text-gray-400 text-xs">Loading…</p>
                <template x-if="!loading">
                    <div class="space-y-3">
                        <div><label class="text-xs font-semibold text-gray-500">Customer Name</label>
                            <input type="text" x-model="form.customer_name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></div>
                        <div><label class="text-xs font-semibold text-gray-500">Company</label>
                            <input type="text" x-model="form.company" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></div>
                        <div><label class="text-xs font-semibold text-gray-500">Address Line 1</label>
                            <input type="text" x-model="form.address_line_1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></div>
                        <div><label class="text-xs font-semibold text-gray-500">Address Line 2</label>
                            <input type="text" x-model="form.address_line_2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></div>
                        <div><label class="text-xs font-semibold text-gray-500">Job/Project Title</label>
                            <input type="text" x-model="form.title" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></div>
                        <div><label class="text-xs font-semibold text-gray-500">By (Staff)</label>
                            <input type="text" x-model="form.by_staff" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></div>

                        <div>
                            <div class="flex items-center justify-between">
                                <label class="text-xs font-semibold text-gray-500">Item</label>
                                <button type="button" @click="addItem()" class="text-xs font-bold text-pink-600 hover:underline">+ Add</button>
                            </div>
                            <div class="mt-1 space-y-2">
                                <template x-for="(row, idx) in form.items" :key="idx">
                                    <div class="rounded-lg border border-gray-200 p-3 space-y-2">
                                        <div class="relative" x-data="itemCombo('{{ route('items.search') }}', '{{ $job->department }}', 'doc')" @click.outside="open = false"><label class="text-[11px] text-gray-400">Item Name — search the library or type your own</label>
                                            <textarea rows="2" x-model="row.item" autocomplete="off" @focus="search(row.item)" @input="search(row.item)" @keydown.escape.stop="open = false" class="block w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                                            <x-item-dropdown /></div>
                                        <div><label class="text-[11px] text-gray-400">Description</label>
                                            <textarea rows="2" x-model="row.desc" class="block w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea></div>
                                        <div class="flex items-end gap-2">
                                            <div class="flex-1"><label class="text-[11px] text-gray-400">Quantity (Qty)</label>
                                                <input type="number" min="0" step="any" x-model="row.qty" class="block w-full rounded-md border-gray-300 shadow-sm text-sm"></div>
                                            <div class="flex-1"><label class="text-[11px] text-gray-400">Price (RM)</label>
                                                <input type="number" min="0" step="0.01" x-model="row.price" class="block w-full rounded-md border-gray-300 shadow-sm text-sm"></div>
                                            <button type="button" @click="removeItem(idx)" class="text-red-500 text-lg leading-none pb-2" title="Remove item">×</button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <template x-if="type !== 'receipt'">
                            <div class="grid grid-cols-2 gap-2">
                                <div><label class="text-xs font-semibold text-gray-500">Delivery (RM)</label>
                                    <input type="number" min="0" step="0.01" x-model="form.delivery" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></div>
                                <div><label class="text-xs font-semibold text-gray-500">Discount (RM)</label>
                                    <input type="number" min="0" step="0.01" x-model="form.discount" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></div>
                            </div>
                        </template>

                        <template x-if="type === 'receipt'">
                            <div class="space-y-3">
                                <div><label class="text-xs font-semibold text-gray-500">Payment Method</label>
                                    <select x-model="form.payment_method" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                        <template x-for="m in paymentMethods" :key="m"><option :value="m" x-text="m"></option></template>
                                    </select></div>
                                <div><label class="text-xs font-semibold text-gray-500">Amount Paid (RM)</label>
                                    <input type="number" min="0" step="0.01" x-model="form.amount_paid" :placeholder="invoiceNumber ? `Auto from Invoice ${invoiceNumber}` : ''" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></div>
                                <div><label class="text-xs font-semibold text-gray-500">Balance Due (RM)</label>
                                    <input type="text" :value="balanceDue.toFixed(2)" readonly class="mt-1 block w-full rounded-md border-gray-200 bg-gray-50 shadow-sm text-sm"></div>
                            </div>
                        </template>

                        <div>
                            <div class="flex items-center justify-between">
                                <label class="text-xs font-semibold text-gray-500">Note</label>
                                <button type="button" @click="toggleNotes()" class="text-xs font-semibold text-gray-500 hover:text-gray-800" x-text="editNotes ? '↺ Use default' : '✏️ Edit Notes'"></button>
                            </div>
                            <template x-if="editNotes">
                                <textarea rows="8" x-model="notesText" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="One note per line"></textarea>
                            </template>
                            <p x-show="!editNotes" class="mt-1 text-xs text-gray-400">Default wording for <span x-text="label.toLowerCase() + 's'"></span> — click "Edit Notes" to adjust for this document.</p>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Live preview (real PDF) --}}
            <div class="relative bg-gray-100 min-h-[300px]" :class="mobileTab === 'form' ? 'hidden lg:block' : ''">
                {{-- Two stacked frames: the new render loads behind the visible one and swaps in on load, so typing never flashes blank. --}}
                <template x-for="i in [0, 1]" :key="i">
                    <iframe class="absolute inset-0 w-full h-full border-0 bg-white" :class="active === i ? 'z-10' : 'z-0'" x-show="previewUrl"
                            :src="frameSrc[i] || 'about:blank'" @load="frameLoaded(i)"></iframe>
                </template>
                <div x-show="previewing" class="absolute top-3 right-4 text-xs text-gray-500 bg-white/90 rounded px-2 py-1 shadow">Updating preview…</div>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2 px-5 py-3 border-t border-gray-100">
            <p x-show="error" x-text="error" class="mr-auto text-xs text-red-600"></p>
            <p x-show="notice && !error" x-text="notice" class="mr-auto text-xs text-green-600"></p>
            <button type="button" @click="close()" class="text-sm font-semibold px-4 py-2 rounded-md border border-gray-200 text-gray-700 hover:bg-gray-50">Cancel</button>
            <button type="button" @click="save()" :disabled="busy || loading" class="text-sm font-semibold px-4 py-2 rounded-md border border-green-500 text-green-600 hover:bg-green-50 disabled:opacity-40">Save</button>
            <button type="button" @click="print()" :disabled="busy || !previewUrl" class="text-sm font-semibold px-4 py-2 rounded-md border border-blue-500 text-blue-600 hover:bg-blue-50 disabled:opacity-40">🖨 Print</button>
            <button type="button" @click="whatsapp()" :disabled="loading" class="text-sm font-semibold px-4 py-2 rounded-md border border-green-500 text-green-600 hover:bg-green-50 disabled:opacity-40">💬 WhatsApp</button>
            <button type="button" @click="download()" :disabled="busy || loading" class="text-sm font-bold px-4 py-2 rounded-md text-white disabled:opacity-40" style="background: #E91E63" x-text="busy ? 'Working…' : 'Download PDF'"></button>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function documentModal(cfg) {
        const blank = () => ({ customer_name: '', company: '', address_line_1: '', address_line_2: '', title: '', by_staff: '',
            items: [], delivery: 0, discount: 0, payment_method: 'Bank Transfer', amount_paid: null });
        return {
            jobCode: cfg.jobCode, urls: cfg.urls, mobileTab: 'form',
            open: false, type: 'quotation', label: 'Quotation', loading: false, busy: false, previewing: false,
            error: '', notice: '', form: blank(), paymentMethods: [], invoiceNumber: null, invoiceTotal: null, customerPhone: '', docNumber: '',
            editNotes: false, notesText: '', defaultNotes: [], previewUrl: null, frameSrc: ['', ''], active: 0, pending: null, dirty: false, timer: null, seq: 0, pageDirty: false,

            url(action) { return this.urls[action].replace('__TYPE__', this.type); },
            token() { return document.querySelector('meta[name="csrf-token"]').content; },
            async call(action, method, body) {
                return fetch(this.url(action), {
                    method, headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.token() },
                    body: body ? JSON.stringify(body) : undefined,
                });
            },
            async failure(res) {
                try { const j = await res.json(); return j.message || Object.values(j.errors || {})[0]?.[0] || 'Something went wrong.'; }
                catch (e) { return 'Something went wrong.'; }
            },
            init() {
                this.$watch('form', () => this.schedule());
                this.$watch('editNotes', () => this.schedule());
                this.$watch('notesText', () => this.schedule());
                // Without this the page behind the modal keeps scrolling on
                // mobile (touch events bubble past the modal's own scroll
                // areas to the body), which felt like the popup wasn't
                // responding to touch at all.
                this.$watch('open', (isOpen) => {
                    document.body.style.overflow = isOpen ? 'hidden' : '';
                });
            },
            async openFor(type) {
                this.type = type; this.open = true; this.loading = true; this.error = ''; this.notice = ''; this.editNotes = false; this.mobileTab = 'form';
                this.resetFrames(); this.form = blank(); this.dirty = false;
                const res = await this.call('draft', 'GET');
                if (!res.ok) { this.error = await this.failure(res); this.loading = false; return; }
                const d = await res.json();
                this.label = d.label; this.docNumber = d.doc_number; this.customerPhone = d.customer_phone || '';
                this.invoiceNumber = d.invoice_number; this.invoiceTotal = d.invoice_total; this.paymentMethods = d.payment_methods;
                this.defaultNotes = d.defaults.notes; this.notesText = d.defaults.notes.join('\n');
                const f = d.defaults; delete f.notes;
                this.loading = false;
                this.form = f;
                this.$nextTick(() => { this.dirty = false; });
                this.refreshPreview();
            },
            resetFrames() {
                this.frameSrc.forEach((u) => u && URL.revokeObjectURL(u.split('#')[0]));
                this.frameSrc = ['', '']; this.active = 0; this.pending = null; this.previewUrl = null;
            },
            frameLoaded(i) {
                if (this.pending !== i) return;
                const old = this.frameSrc[this.active];
                this.active = i; this.pending = null;
                if (old && old !== this.frameSrc[i]) URL.revokeObjectURL(old.split('#')[0]);
                this.frameSrc[1 - i] = '';
            },
            close() {
                if (!this.open) return;
                if (this.dirty && !confirm('Discard your unsaved changes?')) return;
                this.open = false;
                this.resetFrames();
                if (this.pageDirty) window.location.reload();
            },
            addItem() { this.form.items.push({ item: '', desc: '', qty: 1, price: 0 }); },
            removeItem(i) { this.form.items.splice(i, 1); },
            toggleNotes() { this.editNotes = !this.editNotes; if (!this.editNotes) this.notesText = this.defaultNotes.join('\n'); },
            payload() {
                const p = { ...JSON.parse(JSON.stringify(this.form)) };
                if (this.type === 'receipt') { p.delivery = 0; p.discount = 0; }
                else { delete p.payment_method; delete p.amount_paid; }
                if (this.editNotes) p.notes = this.notesText;
                return p;
            },
            get total() {
                const sub = this.form.items.reduce((s, r) => s + (parseFloat(r.qty) || 0) * (parseFloat(r.price) || 0), 0);
                return sub + (parseFloat(this.form.delivery) || 0) - (parseFloat(this.form.discount) || 0);
            },
            get balanceDue() {
                const paid = parseFloat(this.form.amount_paid); const inv = parseFloat(this.invoiceTotal) || 0;
                return Math.max(0, inv - (isNaN(paid) ? inv : paid));
            },
            schedule() {
                if (!this.open || this.loading) return;
                this.dirty = true;
                clearTimeout(this.timer);
                this.timer = setTimeout(() => this.refreshPreview(), 400);
            },
            async refreshPreview() {
                const mine = ++this.seq; this.previewing = true; this.error = '';
                const res = await this.call('preview', 'POST', this.payload());
                if (mine !== this.seq) return;
                this.previewing = false;
                if (!res.ok) { this.error = await this.failure(res); return; }
                const url = URL.createObjectURL(await res.blob());
                const src = url + '#toolbar=0&navpanes=0&view=FitH';
                const t = this.pending ?? (1 - this.active);
                if (this.frameSrc[t]) URL.revokeObjectURL(this.frameSrc[t].split('#')[0]);
                this.pending = t; this.frameSrc[t] = src; this.previewUrl = src;
            },
            async save() {
                this.busy = true; this.error = ''; this.notice = '';
                const res = await this.call('save', 'POST', this.payload());
                this.busy = false;
                if (!res.ok) { this.error = await this.failure(res); return; }
                this.notice = (await res.json()).message; this.pageDirty = true; this.dirty = false;
            },
            async download() {
                this.busy = true; this.error = ''; this.notice = '';
                const res = await this.call('generate', 'POST', this.payload());
                if (!res.ok) { this.busy = false; this.error = await this.failure(res); return; }
                const name = (res.headers.get('Content-Disposition') || '').match(/filename="?([^";]+)"?/)?.[1] || 'document.pdf';
                const a = document.createElement('a');
                a.href = URL.createObjectURL(await res.blob()); a.download = name; document.body.appendChild(a); a.click(); a.remove();
                this.busy = false; this.pageDirty = true; this.dirty = false; this.close();
            },
            print() {
                if (!this.previewUrl) return;
                const f = document.createElement('iframe'); f.style.display = 'none'; f.src = this.previewUrl;
                f.onload = () => { f.contentWindow.focus(); f.contentWindow.print(); };
                document.body.appendChild(f); setTimeout(() => f.remove(), 60000);
            },
            whatsapp() {
                let phone = (this.customerPhone || '').replace(/\D/g, '');
                if (phone.startsWith('0')) phone = '6' + phone;
                const total = this.type === 'receipt' ? (parseFloat(this.form.amount_paid) || 0) : this.total;
                const text = `Hi ${this.form.customer_name || ''}, here is your ${this.label.toLowerCase()} ${this.docNumber} for ${this.form.title} (RM ${total.toFixed(2)}). Thank you!`;
                window.open(`https://wa.me/${phone}?text=${encodeURIComponent(text)}`, '_blank');
            },
        };
    }
</script>
@endpush
