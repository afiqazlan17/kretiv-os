{{-- Finished product photos (e.g. the customer's photo of the installed banner). Optional; they feed the Portfolio page. --}}
<div class="k-card p-5 md:p-6 max-lg:order-last" x-data="jobPhotos('{{ route('jobs.photos.store', $job) }}')">
    <div class="flex items-center justify-between gap-3 mb-1">
        <h3 class="text-base font-bold text-gray-900">Finished Product Photos</h3>
        <a href="{{ route('portfolio.index') }}" class="text-xs font-semibold text-[#C2185B] hover:underline">Portfolio</a>
    </div>
    <p class="text-xs text-gray-400 mb-4">Optional. Photos of the finished work, for example the customer's photo after installing it. They are collected on the Portfolio page.</p>

    @if ($job->photos->isNotEmpty())
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-4">
            @foreach ($job->photos as $photo)
                <div class="rounded-xl border border-[#F5ECE8] overflow-hidden bg-white">
                    <a href="{{ route('jobs.photos.show', [$job, $photo]) }}" target="_blank" rel="noopener">
                        <img src="{{ route('jobs.photos.show', [$job, $photo, 'thumb' => 1]) }}" alt="{{ $photo->caption ?: 'Finished product photo' }}" loading="lazy" class="w-full aspect-square object-cover">
                    </a>
                    <div class="px-2.5 py-2 text-[11px] space-y-1">
                        @if ($photo->caption)<div class="text-gray-700 truncate">{{ $photo->caption }}</div>@endif
                        <div class="flex items-center justify-between gap-2">
                            @can('update', $job)
                                <form method="POST" action="{{ route('jobs.photos.update', [$job, $photo]) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="marketing_ok" value="{{ $photo->marketing_ok ? 0 : 1 }}">
                                    <button type="submit" class="font-semibold {{ $photo->marketing_ok ? 'text-[#047857]' : 'text-gray-400' }}" title="Can this photo be used for marketing?">{{ $photo->marketing_ok ? 'Marketing OK' : 'Internal only' }}</button>
                                </form>
                                <form method="POST" action="{{ route('jobs.photos.destroy', [$job, $photo]) }}" onsubmit="return confirm('Remove this photo?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-gray-400 hover:text-red-500" aria-label="Remove photo"><x-icon name="trash-2" class="w-3.5 h-3.5" /></button>
                                </form>
                            @else
                                <span class="{{ $photo->marketing_ok ? 'text-[#047857]' : 'text-gray-400' }}">{{ $photo->marketing_ok ? 'Marketing OK' : 'Internal only' }}</span>
                            @endcan
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @can('update', $job)
        <div class="flex flex-wrap items-center gap-3">
            <label class="cursor-pointer inline-flex items-center gap-1.5 text-xs font-semibold px-4 py-2 rounded-lg text-white bg-gradient-to-r from-[#E91E63] to-[#F46A3A] hover:brightness-110">
                <x-icon name="image" class="w-3.5 h-3.5" />
                <span x-text="busy ? 'Uploading...' : 'Add photos'"></span>
                <input type="file" accept="image/jpeg,image/png,image/webp" multiple class="hidden" :disabled="busy" @change="upload($event)">
            </label>
            <label class="inline-flex items-center gap-1.5 text-xs text-gray-600">
                <input type="checkbox" x-model="marketing" class="rounded border-gray-300 text-[#C2185B]"> Can be used for marketing
            </label>
        </div>
        <p x-show="error" x-text="error" class="mt-2 text-xs text-red-600"></p>
    @endcan
</div>

@once
@push('scripts')
<script>
    function jobPhotos(url) {
        return {
            busy: false, marketing: true, error: '',
            // Shrunk in the browser first: hosting caps uploads at about 2 MB.
            async shrink(file) {
                try {
                    const img = await createImageBitmap(file);
                    const scale = Math.min(1, 2400 / Math.max(img.width, img.height));
                    const c = document.createElement('canvas');
                    c.width = Math.round(img.width * scale); c.height = Math.round(img.height * scale);
                    const ctx = c.getContext('2d'); ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, c.width, c.height);
                    ctx.drawImage(img, 0, 0, c.width, c.height);
                    const blob = await new Promise((r) => c.toBlob(r, 'image/jpeg', 0.88));
                    return blob ? new File([blob], 'photo.jpg', { type: 'image/jpeg' }) : file;
                } catch (e) { return file; }
            },
            async upload(event) {
                const files = [...event.target.files].slice(0, 20); event.target.value = '';
                if (!files.length) return;
                this.busy = true; this.error = '';
                try {
                    // One request per photo keeps each one well under the upload cap.
                    for (const f of files) {
                        const body = new FormData();
                        body.append('photos[]', await this.shrink(f));
                        body.append('marketing_ok', this.marketing ? 1 : 0);
                        const res = await fetch(url, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body });
                        if (!res.ok) { const j = await res.json().catch(() => ({})); throw new Error(j.message || Object.values(j.errors || {})[0]?.[0] || 'Upload failed.'); }
                    }
                    window.location.reload();
                } catch (e) { this.error = e.message; alert(e.message); this.busy = false; }
            },
        };
    }
</script>
@endpush
@endonce
