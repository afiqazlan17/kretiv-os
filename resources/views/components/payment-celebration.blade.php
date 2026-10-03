{{-- "Money in" popup after Record Payment: coins drop into the bank's logo while the amount counts up.
     Plays once, closes by itself (or tap). $recorded is the session('payment_recorded') payload. --}}
@props(['recorded'])
@php
    $bank = ($recorded['bank'] ?? 'mbb') === 'affin' ? 'affin' : 'mbb';
    $full = (bool) ($recorded['fully_paid'] ?? false);
@endphp
<div x-data="paymentCelebration({{ (float) $recorded['amount'] }}, {{ $full ? 'true' : 'false' }})" x-show="show" x-cloak
     x-transition.opacity.duration.250ms @click="show = false" @keydown.escape.window="show = false"
     class="pay-ov fixed inset-0 z-[80] flex items-center justify-center p-4 bg-black/55">
    <div class="pay-card relative w-full max-w-[340px] rounded-[28px] bg-white px-6 pt-7 pb-5 text-center overflow-hidden" x-ref="card">
        <div class="relative mx-auto mb-1.5 h-[130px] w-[200px]" x-ref="stage">
            <div class="pay-bank absolute left-1/2 bottom-0 -ml-12 h-24 w-24 rounded-[26px] overflow-hidden {{ $bank === 'mbb' ? 'bg-[#FDC300] p-2' : 'bg-[#0565B0]' }}">
                <img src="{{ asset('images/banks/'.($bank === 'mbb' ? 'maybank' : 'affin').'.png') }}" alt="{{ $bank === 'mbb' ? 'Maybank' : 'Affin Bank' }}" class="h-full w-full {{ $bank === 'mbb' ? 'object-contain' : 'object-cover' }}">
            </div>
            <template x-for="(x, i) in [-60, 40, -20, 70, -45]" :key="i">
                <span class="pay-coin" :style="`--x: ${x}px; animation-delay: ${0.35 + i * 0.16}s`">RM</span>
            </template>
        </div>
        <div class="pay-amount text-[34px] font-extrabold leading-tight tracking-tight text-[#047857] tabular-nums" :class="done && 'pay-done'" x-text="'+RM ' + shown"></div>
        <div class="mt-1.5 text-[13px] text-gray-500">Receipt {{ $recorded['number'] }}</div>
        @if ($full)
            <div class="pay-paid mt-3 inline-block rounded-full bg-[#ECFDF5] px-3 py-1 text-xs font-bold text-[#047857]">Job fully paid</div>
            <template x-for="i in 22" :key="'c' + i">
                <span class="pay-confetti" :style="`left: ${Math.random() * 100}%; background: ${['#E91E63', '#F46A3A', '#FCB03C', '#10B981', '#3A86FF'][i % 5]}; animation-delay: ${1.8 + Math.random() * 0.4}s`"></span>
            </template>
        @endif
        <div class="mt-3.5 text-[11px] text-gray-400">Tap anywhere to close</div>
    </div>
</div>

@once
@push('scripts')
<style>
    .pay-card { animation: pay-pop .55s cubic-bezier(.2, 1.4, .4, 1) both; }
    @keyframes pay-pop { from { transform: scale(.6) translateY(30px); opacity: 0; } }
    .pay-bank { box-shadow: 0 14px 30px -12px rgba(0, 0, 0, .35); animation: pay-gulp .35s ease-in-out 1.15s 3; }
    @keyframes pay-gulp { 50% { transform: scale(1.08); } }
    .pay-coin { position: absolute; top: -10px; left: 50%; width: 26px; height: 26px; margin-left: -13px; border-radius: 9999px; opacity: 0;
        background: radial-gradient(circle at 35% 30%, #FFF3B0, #F5B301 60%, #C98B00); border: 2px solid #E0A100; font: 800 11px/22px sans-serif; color: #8A5A00;
        animation: pay-drop .7s cubic-bezier(.5, 0, .9, .6) forwards; }
    @keyframes pay-drop { 0% { opacity: 0; transform: translate(var(--x), -20px) rotate(0); } 15% { opacity: 1; }
        85% { opacity: 1; transform: translate(0, 92px) rotate(260deg) scale(.9); } 100% { opacity: 0; transform: translate(0, 100px) scale(.4); } }
    .pay-done { animation: pay-bounce .4s ease; }
    @keyframes pay-bounce { 40% { transform: scale(1.12); } }
    .pay-paid { opacity: 0; animation: pay-fade .4s ease 1.9s forwards; }
    @keyframes pay-fade { to { opacity: 1; } }
    .pay-confetti { position: absolute; top: -12px; width: 8px; height: 12px; border-radius: 2px; opacity: 0; animation: pay-fall 1.6s ease-in forwards; }
    @keyframes pay-fall { 0% { opacity: 1; transform: translateY(0) rotate(0); } 100% { opacity: 0; transform: translateY(380px) rotate(540deg); } }
    @media (prefers-reduced-motion: reduce) { .pay-card, .pay-bank, .pay-paid { animation: none; opacity: 1; } .pay-coin, .pay-confetti { display: none; } }
</style>
<script>
    function paymentCelebration(amount, full) {
        return {
            show: true, done: false, shown: '0.00',
            init() {
                const fmt = (n) => n.toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                const start = performance.now() + 450, dur = 1200;
                const tick = (now) => {
                    const t = Math.min(1, Math.max(0, (now - start) / dur));
                    this.shown = fmt(amount * (1 - Math.pow(1 - t, 3)));
                    if (t < 1) requestAnimationFrame(tick); else this.done = true;
                };
                requestAnimationFrame(tick);
                setTimeout(() => { this.show = false; }, full ? 4600 : 4000);
            },
        };
    }
</script>
@endpush
@endonce
