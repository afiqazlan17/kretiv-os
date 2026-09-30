{{-- Demo install only: a strip at the bottom of every page. --}}
@if (config('demo.enabled'))
    <style>body { padding-bottom: 34px; }</style>
    <div style="position:fixed;left:0;right:0;bottom:0;z-index:95;background:#100904;color:#FDE68A;font:600 13px/1.4 Figtree,system-ui,sans-serif;text-align:center;padding:8px 12px;">
        Demo mode · {{ config('kretivco.brand.name') }} is a made-up company · Data resets every night · Please don't enter real customer data
    </div>
@endif
