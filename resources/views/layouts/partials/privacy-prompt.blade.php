{{-- Asked once per notice version: staff confirm they've read the Staff Privacy Notice (PDPA proof). --}}
@auth
    @if (! request()->routeIs('privacy.*') && ! auth()->user()->hasAcknowledgedPrivacy())
        <div class="fixed inset-0 z-[90] flex items-center justify-center bg-black/50 p-4" x-data="{ ok: false }">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4 text-sm text-gray-700">
                <div class="flex items-center gap-2.5">
                    <span class="w-9 h-9 rounded-xl bg-[#FFF0F5] text-[#C2185B] flex items-center justify-center"><x-icon name="shield-check" class="w-5 h-5" /></span>
                    <h2 class="text-base font-bold text-gray-900">Staff Privacy Notice</h2>
                </div>
                <p>KretivOS keeps your personal, pay and work records to run payroll, leave and attendance. The notice explains what we keep, who can see it and your rights.</p>
                <p class="text-xs text-gray-500 italic">Notis ini menerangkan data peribadi anda yang kami simpan, siapa boleh melihatnya dan hak anda.</p>
                <a href="{{ route('privacy.staff') }}" class="inline-flex items-center gap-1.5 font-semibold text-[#C2185B] hover:underline"><x-icon name="file-text" class="w-4 h-4" /> Read the notice (English / Bahasa Melayu)</a>
                <label class="flex items-start gap-2.5 cursor-pointer">
                    <input type="checkbox" x-model="ok" class="mt-0.5 rounded text-[#C2185B]">
                    <span>I have read the Staff Privacy Notice.<span class="block text-xs text-gray-500">Saya telah membaca Notis Privasi Staff.</span></span>
                </label>
                <form method="POST" action="{{ route('privacy.acknowledge') }}">
                    @csrf
                    <button type="submit" :disabled="!ok" class="w-full text-sm font-bold py-2.5 rounded-xl text-white bg-gradient-to-r from-[#E91E63] to-[#F46A3A] hover:brightness-110 disabled:opacity-40">Acknowledge</button>
                </form>
            </div>
        </div>
    @endif
@endauth
