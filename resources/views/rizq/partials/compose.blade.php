{{-- Quick note box for Rizq, used on the Rizq page and in the KretivOS pill.
     $action: where it posts; $reload: reload the page after saving; $dark: styling for the OS hero. --}}
<div x-data="rizqCompose('{{ $action }}', {{ ($reload ?? false) ? 'true' : 'false' }})" class="{{ ($dark ?? false) ? '' : 'k-card p-4' }}">
    <textarea x-model="body" rows="3" maxlength="5000" placeholder="Kastam wants 500 boxes, budget 47k, via Glambooth 50/50. Waiting for the LO."
              class="block w-full rounded-lg border-gray-300 text-sm text-gray-800"></textarea>
    <div class="mt-2 flex flex-wrap items-center gap-1.5">
        @foreach (config('kretivco.departments') as $key => $d)
            <button type="button" @click="dept = dept === '{{ $key }}' ? '' : '{{ $key }}'"
                    :class="dept === '{{ $key }}' ? 'text-white border-transparent' : 'bg-white text-gray-600 border-[#EFE3DE]'"
                    :style="dept === '{{ $key }}' ? 'background: {{ $d['color'] }}' : ''"
                    class="text-[11px] font-semibold px-2.5 py-1 rounded-full border">{{ str_replace('Kretiv', '', $d['label']) }}</button>
        @endforeach
        <label class="cursor-pointer inline-flex items-center gap-1 text-[11px] font-semibold px-2.5 py-1 rounded-full border border-[#EFE3DE] bg-white text-gray-600">
            <x-icon name="image" class="w-3 h-3" /> <span x-text="photo ? 'Photo added' : 'Photo'"></span>
            <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="pick($event)">
        </label>
        <button type="button" @click="save()" :disabled="busy" class="ml-auto text-xs font-semibold px-4 py-1.5 rounded-lg text-white bg-gradient-to-r from-[#E91E63] to-[#F46A3A] hover:brightness-110 disabled:opacity-50" x-text="busy ? 'Saving...' : 'Save note'"></button>
    </div>
    <p x-show="error" x-text="error" class="mt-1 text-xs text-red-600"></p>
    <p x-show="saved" x-cloak class="mt-1 text-xs text-green-600">Saved to Rizq.</p>
</div>

@once
@push('scripts')
<script>
    function rizqCompose(url, reload) {
        return {
            body: '', dept: '', photo: null, busy: false, error: '', saved: false,
            async pick(event) {
                const file = event.target.files[0]; event.target.value = '';
                if (!file) return;
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
                if (!this.body.trim()) { this.error = 'Write the note first.'; return; }
                this.busy = true; this.error = ''; this.saved = false;
                const data = new FormData();
                data.append('body', this.body);
                if (this.dept) data.append('department', this.dept);
                if (this.photo) data.append('photo', this.photo);
                try {
                    const res = await fetch(url, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: data });
                    const json = await res.json().catch(() => ({}));
                    if (!res.ok) throw new Error(json.message || Object.values(json.errors || {})[0]?.[0] || 'Could not save. Try again.');
                    if (reload) { window.location.reload(); return; }
                    this.body = ''; this.dept = ''; this.photo = null; this.saved = true;
                    this.$dispatch('rizq-saved', { open: json.open });
                } catch (e) { this.error = e.message; }
                this.busy = false;
            },
        };
    }
</script>
@endpush
@endonce
