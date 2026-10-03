{{-- Sends an existing PDF on WhatsApp with a message (copied for the caption). See sendPdfOnWhatsApp in app.js.
     The PDF is fetched as soon as the button shows: phones only open the share sheet straight from a tap,
     and waiting for the download after the tap made the phone refuse and fall back to text only. --}}
@props(['pdf', 'text', 'phone' => null, 'name' => 'document.pdf'])
<span x-data="{ busy: false, note: '', file: null }" x-init="window.fetchPdfFile(@js($pdf), @js($name)).then((f) => { file = f; }).catch(() => {})" class="inline-flex flex-wrap items-center gap-2">
    <button type="button" :disabled="busy"
            @click="busy = true; note = ''; window.sendPdfOnWhatsApp({ text: @js($text), phone: @js($phone), getFile: () => file ? Promise.resolve(file) : window.fetchPdfFile(@js($pdf), @js($name)) })
                .then((how) => { note = how === 'downloaded' ? 'PDF downloaded. Drag it into the WhatsApp chat.' : (how === 'shared' ? 'Message copied. Paste it as the caption.' : ''); })
                .catch((e) => { note = e.message; }).finally(() => { busy = false; })"
            class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-lg bg-[#25D366] text-white hover:brightness-105 disabled:opacity-60">
        <x-icon name="message-circle" class="w-3.5 h-3.5" /> <span x-text="busy ? 'Preparing...' : 'Send on WhatsApp'"></span>
    </button>
    <span x-show="note" x-text="note" class="text-[11px] text-gray-500"></span>
</span>
