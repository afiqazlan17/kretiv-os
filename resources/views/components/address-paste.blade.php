{{-- "Paste full address": fills the address inputs of the surrounding form (see fillPastedAddress in app.js). --}}
<details class="rounded-lg border border-dashed border-[#EFE3DE] px-3 py-2 text-sm">
    <summary class="cursor-pointer text-xs font-semibold text-[#C2185B]">Paste full address</summary>
    <textarea data-address-paste rows="3" class="mt-2 block w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="e.g. No. 105, Jalan SP 7/14, Bandar Saujana Putra, 42610 Jenjarom, Selangor"></textarea>
    <button type="button" onclick="fillPastedAddress(this)" class="mt-2 text-xs font-semibold px-3 py-1.5 rounded-lg text-white bg-gradient-to-r from-[#E91E63] to-[#F46A3A]">Fill in the address</button>
    <p class="mt-1 text-[11px] text-gray-400">Splits it into the fields below. Check them before saving.</p>
</details>
