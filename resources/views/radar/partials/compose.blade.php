{{-- Quick add box for Radar, used on the Radar page and in the KretivOS orb.
     Only the text is needed; type, date and attachment are one tap each.
     A date written in the text ("SSM expires 15 Nov", "renew 3/12") fills the due date.
     $action: where it posts; $reload: reload the page after saving; $dark: no card wrapper (inside the orb popover). --}}
<div x-data="radarCompose('{{ $action }}', {{ ($reload ?? false) ? 'true' : 'false' }})" class="{{ ($dark ?? false) ? '' : 'k-card p-4' }}">
    <textarea x-model="body" @input="detect()" rows="3" maxlength="5000" placeholder="Meeting with Kastam 15 Nov, 10am. Or: renew SSM by 3/12"
              class="block w-full rounded-lg border-gray-300 text-sm text-gray-800"></textarea>
    <div class="mt-2 flex flex-wrap items-center gap-1.5">
        @foreach (\App\Models\RadarItem::TYPES as $key => $label)
            <button type="button" @click="type = type === '{{ $key }}' ? '' : '{{ $key }}'; typeManual = true"
                    :class="type === '{{ $key }}' ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-600 border-[#EFE3DE]'"
                    class="text-[11px] font-semibold px-2.5 py-1 rounded-full border">{{ $label }}</button>
        @endforeach
        <span class="w-px h-4 bg-[#EFE3DE] mx-0.5"></span>
        <label class="relative inline-flex items-center gap-1 text-[11px] font-semibold px-2.5 py-1 rounded-full border cursor-pointer"
               :class="due ? 'bg-amber-50 text-amber-800 border-amber-200' : 'bg-white text-gray-600 border-[#EFE3DE]'">
            <x-icon name="calendar-clock" class="w-3 h-3" />
            <span x-text="due ? 'Due ' + dueText() : 'Date'"></span>
            <input type="date" x-model="due" @change="dueManual = true" class="absolute inset-0 opacity-0 cursor-pointer" aria-label="Due date">
        </label>
        <button type="button" x-show="due" x-cloak @click="due = ''; dueManual = true" class="text-[11px] text-gray-400 hover:text-red-500" aria-label="Remove date">Remove date</button>
        <label class="cursor-pointer inline-flex items-center gap-1 text-[11px] font-semibold px-2.5 py-1 rounded-full border border-[#EFE3DE] bg-white text-gray-600">
            <x-icon name="paperclip" class="w-3 h-3" /> <span x-text="photo ? 'Attached' : 'Attachment'"></span>
            <input type="file" accept="image/jpeg,image/png,image/webp,application/pdf" class="hidden" @change="pick($event)">
        </label>
    </div>
    <div class="mt-2 flex items-center">
        <button type="button" @click="save()" :disabled="busy" class="ml-auto text-xs font-semibold px-4 py-1.5 rounded-lg text-white bg-gradient-to-r from-[#E91E63] to-[#F46A3A] hover:brightness-110 disabled:opacity-50" x-text="busy ? 'Saving...' : 'Save'"></button>
    </div>
    <p x-show="due && !dueManual" x-cloak class="mt-1 text-[11px] text-gray-400">Date read from your text. Tap the date to change it.</p>
    <p x-show="error" x-text="error" class="mt-1 text-xs text-red-600"></p>
    <p x-show="saved" x-cloak class="mt-1 text-xs text-green-600">Saved to Radar.</p>
</div>

@once
@push('scripts')
<script>
    // Finds a date in free text: "15 Nov", "15 November 2026", "3 Mac", "15/11", "15-11-2026", "2026-11-15".
    // No year means the next time that date comes round. Returns YYYY-MM-DD or ''.
    window.radarFindDate = function (text) {
        const months = { jan: 1, feb: 2, mac: 3, mar: 3, apr: 4, mei: 5, may: 5, jun: 6, jul: 7, ogo: 8, aug: 8, sep: 9, okt: 10, oct: 10, nov: 11, dis: 12, dec: 12 };
        const today = new Date(); today.setHours(0, 0, 0, 0);
        const make = (d, m, y) => {
            if (m < 1 || m > 12 || d < 1 || d > 31) return '';
            let year = y ? (y < 100 ? 2000 + y : y) : today.getFullYear();
            let date = new Date(year, m - 1, d);
            if (date.getMonth() !== m - 1) return '';
            if (!y && date < today) date = new Date(year + 1, m - 1, d);
            return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
        };
        let m = text.match(/\b(\d{4})-(\d{1,2})-(\d{1,2})\b/);
        if (m) return make(+m[3], +m[2], +m[1]);
        m = text.match(/\b(\d{1,2})(?:st|nd|rd|th|hb)?\s+(jan|feb|mac|mar|apr|mei|may|jun|jul|ogo|aug|sep|okt|oct|nov|dis|dec)[a-z]*\.?(?:\s+(\d{4}))?\b/i);
        if (m) return make(+m[1], months[m[2].toLowerCase()], m[3] ? +m[3] : null);
        m = text.match(/(?:^|[^\d\/.\-])(\d{1,2})[\/.\-](\d{1,2})(?:[\/.\-](\d{2}|\d{4}))?(?![\d\/.\-])/);
        if (m) return make(+m[1], +m[2], m[3] ? +m[3] : null);
        return '';
    };

    function radarCompose(url, reload) {
        return {
            body: '', type: '', typeManual: false, due: '', dueManual: false, dept: '', photo: null, busy: false, error: '', saved: false,
            detect() {
                if (!this.dueManual) this.due = window.radarFindDate(this.body);
            },
            dueText() {
                return new Date(this.due + 'T00:00:00').toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
            },
            async pick(event) {
                const file = event.target.files[0]; event.target.value = '';
                if (!file) return;
                if (file.type === 'application/pdf') { this.photo = file; return; }
                // Shrunk in the browser first: hosting caps uploads at about 2 MB.
                try {
                    const img = await createImageBitmap(file);
                    const scale = Math.min(1, 2000 / Math.max(img.width, img.height));
                    const c = document.createElement('canvas');
                    c.width = Math.round(img.width * scale); c.height = Math.round(img.height * scale);
                    const ctx = c.getContext('2d'); ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, c.width, c.height);
                    ctx.drawImage(img, 0, 0, c.width, c.height);
                    const blob = await new Promise((r) => c.toBlob(r, 'image/jpeg', 0.88));
                    this.photo = blob ? new File([blob], 'photo.jpg', { type: 'image/jpeg' }) : file;
                } catch (e) { this.photo = file; }
            },
            async save() {
                if (!this.body.trim()) { this.error = 'Write something first.'; return; }
                this.busy = true; this.error = ''; this.saved = false;
                const data = new FormData();
                data.append('body', this.body);
                if (this.type) data.append('type', this.type);
                if (this.due) data.append('due_date', this.due);
                if (this.photo) data.append('photo', this.photo);
                try {
                    const res = await fetch(url, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: data });
                    const json = await res.json().catch(() => ({}));
                    if (!res.ok) throw new Error(json.message || Object.values(json.errors || {})[0]?.[0] || 'Could not save. Try again.');
                    if (reload) { window.location.reload(); return; }
                    Object.assign(this, { body: '', type: '', typeManual: false, due: '', dueManual: false, dept: '', photo: null, saved: true });
                    this.$dispatch('radar-saved', { count: json.count });
                } catch (e) { this.error = e.message; }
                this.busy = false;
            },
        };
    }
</script>
@endpush
@endonce
