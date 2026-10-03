{{-- Preview / edit modal for Quotation, Proforma, Invoice and Receipt. The right-hand
     preview is the real dompdf output (POST .../preview), so what you see is what
     Download PDF produces. --}}
<div x-data="documentModal(@js([
        'jobCode' => $job->job_id,
        'urls' => collect(['draft', 'preview', 'save', 'generate'])->mapWithKeys(fn ($a) => [$a => route('jobs.documents.'.$a, [$job, '__TYPE__'])])->all()
            + ['itemImage' => route('jobs.item-images.store', $job), 'itemImageShow' => route('jobs.item-images.show', [$job, '__NAME__'])],
    ]))"
     @open-document.window="openFor($event.detail.type)"
     @keydown.escape.window="open && close()"
     x-show="open" x-cloak
     class="fixed inset-0 z-[70] flex items-center justify-center bg-black/50 p-2 sm:p-6">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-6xl h-[92vh] flex flex-col overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-[#F5ECE8]">
            <h2 class="text-base font-bold text-gray-900" x-text="`Preview ${label} · ${scope === 'project' ? 'Whole Project' : jobCode}`"></h2>
            <button type="button" @click="close()" class="text-gray-400 hover:text-gray-700" aria-label="Close"><x-icon name="x" class="w-5 h-5" /></button>
        </div>

        {{-- On phones, the form and the live PDF preview used to be squeezed into one
             scrolling column with no fixed height, which made typing feel like it was
             fighting the preview for space. A tab switch lets you fully hide one side. --}}
        <div class="lg:hidden flex gap-2 px-5 pt-3">
            <button type="button" @click="mobileTab = 'form'" class="flex-1 text-xs font-semibold px-3 py-1.5 rounded-xl border"
                    :class="mobileTab === 'form' ? 'bg-[#C2185B] text-white border-[#C2185B]' : 'border-[#EFE3DE] text-gray-600'">Form</button>
            <button type="button" @click="mobileTab = 'preview'; $nextTick(() => pager.render($refs.mobileCanvas))" class="flex-1 text-xs font-semibold px-3 py-1.5 rounded-xl border"
                    :class="mobileTab === 'preview' ? 'bg-[#C2185B] text-white border-[#C2185B]' : 'border-[#EFE3DE] text-gray-600'">Preview</button>
        </div>
        <div class="flex-1 min-h-0 grid grid-cols-1 auto-rows-fr lg:grid-cols-[minmax(0,380px)_minmax(0,1fr)] lg:auto-rows-auto">
            {{-- Form --}}
            <div class="overflow-y-auto p-5 space-y-3 border-r border-[#F5ECE8] text-sm" :class="mobileTab === 'preview' ? 'hidden lg:block' : ''" @input="schedule()" @change="schedule()" @keyup="schedule()">
                <p x-show="loading" class="text-gray-400 text-xs">Loading…</p>
                <template x-if="!loading">
                    <div class="space-y-3">
                        <template x-if="project">
                            <div class="rounded-xl border border-[#EFE3DE] bg-[#FFF9F6] p-3 space-y-2">
                                <p class="text-xs font-semibold text-gray-600">This job is part of a project. Send the customer one document for all of it?</p>
                                <div class="grid grid-cols-2 gap-1 rounded-lg bg-[#F3EAE6] p-1">
                                    <button type="button" @click="setScope('project')" class="text-xs font-semibold px-2 py-1.5 rounded-md" :class="scope === 'project' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500'">Whole project</button>
                                    <button type="button" @click="setScope('job')" class="text-xs font-semibold px-2 py-1.5 rounded-md" :class="scope === 'job' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500'">This job only</button>
                                </div>
                                <ul x-show="scope === 'project'" class="text-xs text-gray-600 space-y-0.5">
                                    <template x-for="j in project.jobs" :key="j.job_id">
                                        <li class="flex justify-between gap-2"><span class="truncate" x-text="`${j.job_id} · ${j.department} · ${j.title}`"></span><span class="shrink-0" x-text="'RM ' + Number(j.total).toFixed(2)"></span></li>
                                    </template>
                                </ul>
                                <p x-show="scope === 'project' && project.left_out.length" class="text-[11px] text-gray-400" x-text="'Not included: ' + project.left_out.join(', ')"></p>
                            </div>
                        </template>
                        {{-- Document language: fixed wording and standard notes switch; what staff typed stays as typed. Remembered for this customer. --}}
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-xs font-semibold text-gray-500">Language</span>
                            <div class="inline-flex p-0.5 rounded-lg bg-[#F7F1EE] text-xs font-semibold">
                                <button type="button" @click="setLang('en')" :class="form.lang === 'en' ? 'bg-white shadow text-gray-900' : 'text-gray-500'" class="px-3 py-1 rounded-md">English</button>
                                <button type="button" @click="setLang('ms')" :class="form.lang === 'ms' ? 'bg-white shadow text-gray-900' : 'text-gray-500'" class="px-3 py-1 rounded-md">Bahasa Melayu</button>
                            </div>
                        </div>
                        <div><label class="text-xs font-semibold text-gray-500">Customer Name</label>
                            <input type="text" x-model="form.customer_name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></div>
                        <div><label class="text-xs font-semibold text-gray-500">Company</label>
                            <input type="text" x-model="form.company" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></div>
                        <div><label class="text-xs font-semibold text-gray-500">Phone</label>
                            <input type="text" x-model="form.phone" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></div>
                        <div><label class="text-xs font-semibold text-gray-500">Address Line 1</label>
                            <input type="text" x-model="form.address_line_1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></div>
                        <div><label class="text-xs font-semibold text-gray-500">Address Line 2</label>
                            <input type="text" x-model="form.address_line_2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></div>
                        <div><label class="text-xs font-semibold text-gray-500">Job/Project Title</label>
                            <input type="text" x-model="form.title" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></div>
                        <div><label class="text-xs font-semibold text-gray-500">By (Staff)</label>
                            <input type="text" x-model="form.by_staff" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></div>

                        <p x-show="scope === 'project'" class="rounded-lg bg-[#F7F1EE] px-3 py-2 text-xs text-gray-500">Items, delivery and discount come from each job. To change them, edit that job, or switch to This job only.</p>
                        <div x-show="type !== 'credit_note' && scope !== 'project'">
                            <div class="flex items-center justify-between">
                                <label class="text-xs font-semibold text-gray-500">Item</label>
                                <button type="button" @click="addItem()" class="inline-flex items-center gap-1 text-xs font-bold text-[#C2185B] hover:underline"><x-icon name="plus" class="w-3.5 h-3.5" /> Add</button>
                            </div>
                            <div class="mt-1 space-y-2">
                                <template x-for="(row, idx) in form.items" :key="idx">
                                    <div class="rounded-xl border border-[#EFE3DE] p-3 space-y-2">
                                        <div class="relative" x-data="itemCombo('{{ route('items.search') }}', '{{ $job->department }}', 'doc')" @click.outside="open = false"><label class="text-[11px] text-gray-400">Item Name (search the library or type your own)</label>
                                            <textarea rows="2" x-model="row.item" autocomplete="off" @focus="search(row.item)" @input="search(row.item)" @keydown.escape.stop="open = false" class="block w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                                            <x-item-dropdown /></div>
                                        <div><label class="text-[11px] text-gray-400">Description</label>
                                            <textarea rows="2" x-model="row.desc" class="block w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea></div>
                                        <div x-show="type !== 'receipt'" class="flex items-center gap-3">
                                            <template x-if="row.image">
                                                <a :href="imageUrl(row.image)" target="_blank" rel="noopener"><img :src="imageUrl(row.image)" alt="Item picture" class="h-14 w-14 rounded-md border border-[#EFE3DE] object-cover"></a>
                                            </template>
                                            <label class="cursor-pointer text-xs font-semibold text-[#C2185B] hover:underline">
                                                <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="uploadImage($event, row)">
                                                <span x-text="row.uploading ? 'Uploading...' : (row.image ? 'Change picture' : 'Add picture (mockup)')"></span>
                                            </label>
                                            <button type="button" x-show="row.image" @click="row.image = ''" class="text-xs text-gray-400 hover:text-red-500">Remove</button>
                                        </div>
                                        <div class="flex items-end gap-2">
                                            <div class="flex-1"><label class="text-[11px] text-gray-400">Quantity (Qty)</label>
                                                <input type="number" min="0" step="any" x-model="row.qty" class="block w-full rounded-md border-gray-300 shadow-sm text-sm"></div>
                                            <div class="flex-1" x-show="type !== 'delivery'"><label class="text-[11px] text-gray-400">Price (RM)</label>
                                                <input type="number" min="0" step="0.01" x-model="row.price" class="block w-full rounded-md border-gray-300 shadow-sm text-sm"></div>
                                            <button type="button" @click="removeItem(idx)" class="text-gray-400 hover:text-red-500 pb-2.5" title="Remove item" aria-label="Remove item"><x-icon name="trash-2" class="w-4 h-4" /></button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <template x-if="!['receipt', 'delivery', 'credit_note'].includes(type) && scope !== 'project'">
                            <div class="grid grid-cols-2 gap-2">
                                <div><label class="text-xs font-semibold text-gray-500">Delivery (RM)</label>
                                    <input type="number" min="0" step="0.01" x-model="form.delivery" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></div>
                                <div><label class="text-xs font-semibold text-gray-500">Discount (RM)</label>
                                    <input type="number" min="0" step="0.01" x-model="form.discount" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></div>
                            </div>
                        </template>

                        <template x-if="type === 'quotation'">
                            <div><label class="text-xs font-semibold text-gray-500">Valid For (days)</label>
                                <input type="number" min="1" max="365" x-model="form.valid_days" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <p class="mt-1 text-[11px] text-gray-400">Valid until <span x-text="validUntil"></span>. Standard is 14 days; change it for tenders. Update the matching note too.</p></div>
                        </template>

                        <template x-if="type === 'invoice' || type === 'proforma'">
                            <div><label class="text-xs font-semibold text-gray-500">Due Date</label>
                                <input type="date" x-model="form.due_date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <p class="mt-1 text-[11px] text-gray-400" x-text="type === 'invoice' ? 'Defaults to 14 days from today.' : 'Defaults to 7 days from today.'"></p>
                                <p x-show="type === 'invoice' && paidBefore > 0" class="mt-1 text-[11px] text-[#047857]">Deposit already received (RM <span x-text="paidBefore.toFixed(2)"></span>) is taken off the balance on this invoice.</p></div>
                        </template>

                        <template x-if="type === 'credit_note'">
                            <div class="space-y-3">
                                <div class="rounded-lg bg-[#FFF7ED] px-3 py-2 text-xs text-[#9A3412]">
                                    Against Invoice <b x-text="invoiceNumber"></b>. Up to <b x-text="'RM ' + (parseFloat(invoiceTotal) || 0).toFixed(2)"></b> can be credited. It comes off revenue and what the customer owes.
                                </div>
                                <div><label class="text-xs font-semibold text-gray-500">Reason</label>
                                    <select x-model="form.credit_reason" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                        <template x-for="(lbl, key) in creditReasons" :key="key"><option :value="key" x-text="lbl"></option></template>
                                    </select></div>
                                <div><label class="text-xs font-semibold text-gray-500">Details (optional)</label>
                                    <input type="text" x-model="form.credit_reason_text" maxlength="255" placeholder="e.g. 10% loyalty discount agreed on 3 Oct" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></div>
                                <div><label class="text-xs font-semibold text-gray-500">Credit Amount (RM)</label>
                                    <input type="number" min="0" step="0.01" x-model="form.credit_amount" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></div>
                            </div>
                        </template>

                        <template x-if="type === 'receipt'">
                            <div class="space-y-3">
                                <div><label class="text-xs font-semibold text-gray-500">Payment Method</label>
                                    <select x-model="form.payment_method" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                        <template x-for="m in paymentMethods" :key="m"><option :value="m" x-text="m"></option></template>
                                    </select></div>
                                <div x-show="!invoiceNumber" class="rounded-lg bg-[#EFF6FF] px-3 py-2 text-xs text-[#1D4ED8]">
                                    No invoice yet, so this is a deposit against the quoted total of <b x-text="'RM ' + (parseFloat(invoiceTotal) || 0).toFixed(2)"></b>. The invoice will show it as already received.
                                </div>
                                <div x-show="paidBefore > 0" class="rounded-lg bg-[#ECFDF5] px-3 py-2 text-xs text-[#047857]">
                                    Already paid on earlier receipts: <b x-text="'RM ' + paidBefore.toFixed(2)"></b>. This receipt records the next payment.
                                </div>
                                <div><label class="text-xs font-semibold text-gray-500">Amount Paid Now (RM)</label>
                                    <input type="number" min="0" step="0.01" x-model="form.amount_paid" :placeholder="invoiceNumber ? `Auto from Invoice ${invoiceNumber}` : 'Deposit against the quoted total'" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></div>
                                <div><label class="text-xs font-semibold text-gray-500">Balance Due (RM)</label>
                                    <input type="text" :value="balanceDue.toFixed(2)" readonly class="mt-1 block w-full rounded-md border-gray-200 !bg-[#F7F1EE] shadow-sm text-sm"></div>
                            </div>
                        </template>

                        <div>
                            <div class="flex items-center justify-between">
                                <label class="text-xs font-semibold text-gray-500">Note</label>
                                <button type="button" @click="toggleNotes()" class="text-xs font-semibold text-gray-500 hover:text-gray-800" x-text="editNotes ? 'Use default' : 'Edit Notes'"></button>
                            </div>
                            <template x-if="editNotes">
                                <div class="mt-1 space-y-1.5">
                                    <template x-for="(n, i) in noteList" :key="i">
                                        <div class="flex items-start gap-2">
                                            <span class="w-5 pt-2 text-right text-sm font-semibold text-gray-400" x-text="(i + 1) + '.'"></span>
                                            <textarea rows="2" x-model="noteList[i]" @keydown.enter.prevent="addNote(i + 1)" class="block w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="Note"></textarea>
                                            <button type="button" @click="noteList.splice(i, 1)" class="pt-2 text-gray-400 hover:text-red-500" title="Remove note" aria-label="Remove note"><x-icon name="trash-2" class="w-4 h-4" /></button>
                                        </div>
                                    </template>
                                    <button type="button" @click="addNote(noteList.length)" class="inline-flex items-center gap-1 pl-7 text-xs font-bold text-[#C2185B] hover:underline"><x-icon name="plus" class="w-3.5 h-3.5" /> Add note</button>
                                </div>
                            </template>
                            <p x-show="!editNotes" class="mt-1 text-xs text-gray-400">Uses the default wording for <span x-text="label.toLowerCase() + 's'"></span>. Click "Edit Notes" to change it for this job.</p>
                            <p x-show="editNotes" class="mt-1 text-xs text-gray-400">These notes are kept for this job once you Save or Download. "Use default" switches back to the standard wording.</p>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Live preview (real PDF). Desktop keeps the native iframe viewer (scroll/zoom
                 work fine with a mouse). Mobile can't reliably page or scroll a PDF embedded
                 in an iframe, so it gets its own canvas render with Prev/Next controls. --}}
            <div class="relative bg-[#F7F1EE] min-h-[300px] hidden lg:block">
                {{-- Two stacked frames: the new render loads behind the visible one and swaps in on load, so typing never flashes blank. --}}
                <template x-for="i in [0, 1]" :key="i">
                    <iframe class="absolute inset-0 w-full h-full border-0 bg-white" :class="active === i ? 'z-10' : 'z-0'" x-show="previewUrl"
                            :src="frameSrc[i] || 'about:blank'" @load="frameLoaded(i)"></iframe>
                </template>
                <div x-show="previewing" class="absolute top-3 right-4 text-xs text-gray-500 bg-white/90 rounded px-2 py-1 shadow">Updating preview…</div>
            </div>
            <div class="lg:hidden bg-[#F7F1EE] flex flex-col" :class="mobileTab === 'form' ? 'hidden' : 'flex'">
                <div class="flex-1 overflow-auto flex items-start justify-center p-2">
                    <p x-show="pager.error" x-text="pager.error" class="text-xs text-red-600 text-center p-4"></p>
                    <canvas x-show="!pager.error" x-ref="mobileCanvas" class="shadow bg-white"></canvas>
                </div>
                <div class="flex items-center justify-center gap-3 px-3 py-2 border-t border-[#F5ECE8] bg-white text-sm">
                    <button type="button" @click="pager.prev($refs.mobileCanvas)" :disabled="pager.pageNum <= 1"
                            class="inline-flex items-center gap-1 px-3 py-1 rounded-lg border border-[#EFE3DE] disabled:opacity-30"><x-icon name="chevron-right" class="w-4 h-4 rotate-180" /> Prev</button>
                    <span class="text-xs text-gray-500" x-text="`Page ${pager.pageNum} / ${pager.numPages}`"></span>
                    <button type="button" @click="pager.next($refs.mobileCanvas)" :disabled="pager.pageNum >= pager.numPages"
                            class="inline-flex items-center gap-1 px-3 py-1 rounded-lg border border-[#EFE3DE] disabled:opacity-30">Next <x-icon name="chevron-right" class="w-4 h-4" /></button>
                </div>
                <div x-show="previewing || pager.canvasBusy" class="absolute top-3 right-4 text-xs text-gray-500 bg-white/90 rounded px-2 py-1 shadow">Updating preview…</div>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2 px-5 py-3 border-t border-[#F5ECE8]">
            <p x-show="error" x-text="error" class="mr-auto text-xs text-red-600"></p>
            <p x-show="notice && !error" x-text="notice" class="mr-auto text-xs text-green-600"></p>
            <p x-show="!notice && !error" class="sm:hidden mr-auto text-[11px] text-gray-400">WhatsApp copies the message for you. Paste it as the caption.</p>
            <button type="button" @click="close()" class="text-sm font-semibold px-4 py-2 rounded-xl border border-[#EFE3DE] text-gray-700 hover:bg-[#FFF7F3]">Cancel</button>
            <button type="button" x-show="scope !== 'project'" @click="save()" :disabled="busy || loading" class="inline-flex items-center gap-1.5 text-sm font-semibold px-4 py-2 rounded-xl border border-green-500 text-green-700 hover:bg-green-50 disabled:opacity-40"><x-icon name="save" class="w-4 h-4" /> Save</button>
            <button type="button" @click="print()" :disabled="busy || !previewUrl" class="inline-flex items-center gap-1.5 text-sm font-semibold px-4 py-2 rounded-xl border border-blue-500 text-blue-600 hover:bg-blue-50 disabled:opacity-40"><x-icon name="printer" class="w-4 h-4" /> Print</button>
            <button type="button" @click="whatsapp()" :disabled="busy || loading" class="inline-flex items-center gap-1.5 text-sm font-semibold px-4 py-2 rounded-xl border border-green-500 text-green-700 hover:bg-green-50 disabled:opacity-40"><x-icon name="message-circle" class="w-4 h-4" /> WhatsApp</button>
            <button type="button" @click="download()" :disabled="busy || loading" class="text-sm font-bold px-4 py-2 rounded-xl text-white bg-gradient-to-r from-[#E91E63] to-[#F46A3A] hover:brightness-110 disabled:opacity-40" x-text="busy ? 'Working…' : 'Download PDF'"></button>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function documentModal(cfg) {
        const blank = () => ({ customer_name: '', company: '', phone: '', address_line_1: '', address_line_2: '', title: '', by_staff: '', due_date: '',
            items: [], delivery: 0, discount: 0, payment_method: 'Online Banking', amount_paid: null, credit_reason: 'discount', credit_reason_text: '', credit_amount: null });
        return {
            jobCode: cfg.jobCode, urls: cfg.urls, mobileTab: 'form', scope: 'project', project: null, projectTotal: 0,
            open: false, type: 'quotation', label: 'Quotation', loading: false, busy: false, previewing: false,
            error: '', notice: '', form: blank(), paymentMethods: [], creditReasons: {}, invoiceNumber: null, invoiceTotal: null, paidBefore: 0, customerPhone: '', docNumber: '',
            editNotes: false, notesText: '', noteList: [], standardNotes: { en: [], ms: [] }, previewUrl: null, previewBlob: null, previewName: '', frameSrc: ['', ''], active: 0, pending: null, dirty: false, timer: null, seq: 0, pageDirty: false,
            pager: window.createPdfPager(),

            url(action) { return this.urls[action].replace('__TYPE__', this.type) + (this.scope === 'project' ? '?scope=project' : ''); },
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
                // One box per note, numbered like the PDF; stored as one line each.
                this.$watch('noteList', (list) => { this.notesText = list.map((n) => n.replace(/\s*\n\s*/g, ' ')).join('\n'); });
                // Without this the page behind the modal keeps scrolling on
                // mobile (touch events bubble past the modal's own scroll
                // areas to the body), which felt like the popup wasn't
                // responding to touch at all.
                this.$watch('open', (isOpen) => {
                    document.body.style.overflow = isOpen ? 'hidden' : '';
                });
            },
            // A job in a project opens on the whole-project document; the server falls back to this job alone when there's nothing to combine.
            openFor(type) { this.type = type; this.scope = 'project'; this.open = true; this.mobileTab = 'form'; this.load(); },
            setScope(scope) {
                if (scope === this.scope) return;
                if (this.dirty && !confirm('Discard your unsaved changes?')) return;
                this.scope = scope; this.load();
            },
            async load() {
                this.loading = true; this.error = ''; this.notice = ''; this.editNotes = false;
                this.resetFrames(); this.form = blank(); this.dirty = false;
                const res = await this.call('draft', 'GET');
                if (!res.ok) { this.error = await this.failure(res); this.loading = false; return; }
                const d = await res.json();
                this.scope = d.scope; this.project = d.project; this.projectTotal = d.project_total || 0;
                this.label = d.label; this.docNumber = d.doc_number; this.customerPhone = d.customer_phone || '';
                this.invoiceNumber = d.invoice_number; this.invoiceTotal = d.invoice_total; this.paidBefore = d.paid_before || 0; this.paymentMethods = d.payment_methods; this.creditReasons = d.credit_reasons || {};
                // Standard wording for "Use default"; the job's own saved notes (if any) open in edit mode.
                this.standardNotes = d.standard_notes; this.notesText = d.defaults.notes.join('\n'); this.noteList = [...d.defaults.notes]; this.editNotes = d.notes_custom;
                const f = d.defaults; delete f.notes;
                this.loading = false;
                this.form = f;
                this.$nextTick(() => { this.dirty = false; });
                this.refreshPreview();
            },
            resetFrames() {
                this.frameSrc.forEach((u) => u && URL.revokeObjectURL(u.split('#')[0]));
                this.frameSrc = ['', '']; this.active = 0; this.pending = null; this.previewUrl = null;
                this.pager.reset();
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
            addItem() { this.form.items.push({ item: '', desc: '', qty: 1, price: 0, image: '' }); },
            imageUrl(name) { return this.urls.itemImageShow.replace('__NAME__', name); },
            async uploadImage(event, row) {
                const file = event.target.files[0]; event.target.value = '';
                if (!file) return;
                row.uploading = true; this.error = '';
                // Shrink in the browser first: hosting caps uploads at about 2 MB, and phone photos and mockups are often bigger.
                let upload = file;
                try {
                    const img = await createImageBitmap(file);
                    const scale = Math.min(1, 2000 / Math.max(img.width, img.height));
                    const canvas = document.createElement('canvas');
                    canvas.width = Math.round(img.width * scale); canvas.height = Math.round(img.height * scale);
                    const ctx = canvas.getContext('2d');
                    ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, canvas.width, canvas.height);
                    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                    const blob = await new Promise((r) => canvas.toBlob(r, 'image/jpeg', 0.88));
                    if (blob) upload = new File([blob], 'picture.jpg', { type: 'image/jpeg' });
                } catch (e) { /* send the original */ }
                const body = new FormData(); body.append('file', upload);
                try {
                    const res = await fetch(this.urls.itemImage, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.token() }, body });
                    if (res.ok) row.image = (await res.json()).image; else { this.error = await this.failure(res); alert(this.error); }
                } catch (e) { this.error = 'Upload failed. Check the connection and try again.'; }
                row.uploading = false;
            },
            removeItem(i) { this.form.items.splice(i, 1); },
            get defaultNotes() { return this.standardNotes[this.form.lang || 'en'] || []; },
            // Standard notes follow the language; notes staff wrote themselves are left alone.
            setLang(lang) {
                const before = JSON.stringify(this.noteList.map((n) => n.trim()).filter(Boolean));
                const wasStandard = !this.editNotes || before === JSON.stringify(this.defaultNotes);
                this.form.lang = lang;
                if (wasStandard) { this.noteList = [...this.defaultNotes]; this.notesText = this.defaultNotes.join('\n'); }
            },
            toggleNotes() { this.editNotes = !this.editNotes; if (!this.editNotes) { this.notesText = this.defaultNotes.join('\n'); this.noteList = [...this.defaultNotes]; } },
            addNote(at) { this.noteList.splice(at, 0, ''); this.$nextTick(() => this.$root.querySelectorAll('textarea[placeholder="Note"]')[at]?.focus()); },
            payload() {
                const p = { ...JSON.parse(JSON.stringify(this.form)) };
                (p.items || []).forEach((r) => delete r.uploading);
                if (this.type === 'receipt' || this.type === 'credit_note') { p.delivery = 0; p.discount = 0; }
                else { delete p.payment_method; delete p.amount_paid; }
                if (this.editNotes) p.notes = this.notesText; else p.use_default_notes = true;
                return p;
            },
            get total() {
                if (this.type === 'credit_note') return parseFloat(this.form.credit_amount) || 0;
                if (this.scope === 'project') return this.projectTotal;
                const sub = this.form.items.reduce((s, r) => s + (parseFloat(r.qty) || 0) * (parseFloat(r.price) || 0), 0);
                return sub + (parseFloat(this.form.delivery) || 0) - (parseFloat(this.form.discount) || 0);
            },
            get validUntil() {
                const d = new Date(); d.setDate(d.getDate() + (parseInt(this.form.valid_days) || 14));
                return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
            },
            get balanceDue() {
                const paid = parseFloat(this.form.amount_paid); const owed = (parseFloat(this.invoiceTotal) || 0) - this.paidBefore;
                return Math.max(0, owed - (isNaN(paid) ? owed : paid));
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
                const blob = await res.blob();
                this.previewBlob = blob;
                this.previewName = (res.headers.get('Content-Disposition') || '').match(/filename="?([^";]+)"?/)?.[1] || `${this.docNumber}.pdf`;
                const url = URL.createObjectURL(blob);
                const src = url + '#toolbar=0&navpanes=0&view=FitH';
                const t = this.pending ?? (1 - this.active);
                if (this.frameSrc[t]) URL.revokeObjectURL(this.frameSrc[t].split('#')[0]);
                this.pending = t; this.frameSrc[t] = src; this.previewUrl = src;
                this.pager.load(url, this.$refs.mobileCanvas);
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
            // Phones can't print a PDF inside the page (iPhone showed a 404), so they get the
            // share sheet with the named PDF, which has Print and Save to Files.
            // Computers print from a hidden frame; the page title is swapped so
            // "Save as PDF" suggests the document's name instead of "Kretivco Jobs".
            async print() {
                if (!this.previewUrl || !this.previewBlob) return;
                const name = this.previewName || `${this.docNumber}.pdf`;
                const file = new File([this.previewBlob], name, { type: 'application/pdf' });
                if (window.matchMedia('(pointer: coarse)').matches && navigator.canShare && navigator.canShare({ files: [file] })) {
                    try { await navigator.share({ files: [file] }); } catch (e) { /* closed the sheet */ }
                    return;
                }
                const title = document.title;
                document.title = name.replace(/\.pdf$/i, '');
                const f = document.createElement('iframe'); f.style.display = 'none'; f.src = this.previewUrl;
                f.onload = () => {
                    f.contentWindow.focus(); f.contentWindow.print();
                    setTimeout(() => { document.title = title; }, 1000);
                };
                document.body.appendChild(f); setTimeout(() => f.remove(), 60000);
            },
            /**
             * Issues the document (same as Download), then sends the PDF on
             * WhatsApp with the message copied for the caption (see
             * sendPdfOnWhatsApp in app.js). The modal stays open to say so.
             */
            async whatsapp() {
                const total = this.type === 'receipt' ? (parseFloat(this.form.amount_paid) || 0) : this.total;
                const text = `Hi ${this.form.customer_name || ''}, here is your ${this.label.toLowerCase()} ${this.docNumber} for ${this.form.title} (RM ${total.toFixed(2)}). Thank you!`;
                this.busy = true; this.error = ''; this.notice = '';
                try {
                    const how = await window.sendPdfOnWhatsApp({ text, phone: this.customerPhone, getFile: async () => {
                        const res = await this.call('generate', 'POST', this.payload());
                        if (!res.ok) throw new Error(await this.failure(res));
                        this.pageDirty = true; this.dirty = false;
                        const name = (res.headers.get('Content-Disposition') || '').match(/filename="?([^";]+)"?/)?.[1] || 'document.pdf';
                        return new File([await res.blob()], name, { type: 'application/pdf' });
                    } });
                    this.busy = false;
                    this.notice = how === 'downloaded'
                        ? 'PDF downloaded and WhatsApp opened with the message. Drag the PDF into the chat.'
                        : 'Document issued. The message is copied: in WhatsApp, long-press the caption and Paste.';
                } catch (e) { this.busy = false; this.error = e.message || 'Something went wrong.'; }
            },
        };
    }
</script>
@endpush
