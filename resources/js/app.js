import Alpine from 'alpinejs';
import Quill from 'quill';

window.Alpine = Alpine;

// Cross-component state for the job detail page's Action dropdown (header)
// and its corresponding form panels (main content) — two separate DOM
// subtrees under one Blade layout, so a shared store is simpler than
// threading state through x-data props. Harmless no-op on every other page.
Alpine.store('jobActions', { panel: null });

// Settings page's header "+ Add User" button and the form panel it
// toggles live in the same two separate header/body DOM subtrees.
Alpine.store('settingsUi', { showAdd: false });

// The job detail page's "New Note" composer. Quill only shapes what a
// well-behaved browser sends — the actual security boundary is server-side
// (App\Support\NoteSanitizer), since the hidden `note` input's value can be
// edited directly before submit regardless of what the editor produces.
window.noteComposer = function () {
    return {
        note: '',
        mount(el) {
            this.quill = new Quill(el, {
                theme: 'snow',
                placeholder: 'Add a note about this job...',
                modules: {
                    toolbar: [
                        ['bold', 'italic', 'underline'],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        [{ color: [] }],
                        ['link'],
                        ['clean'],
                    ],
                },
            });
        },
        sync() {
            this.note = this.quill.getText().trim() ? this.quill.root.innerHTML : '';
        },
        reset() {
            this.quill.setText('');
            this.note = '';
        },
    };
};

// Job detail page's "Line Items" editor (quotation/proforma PDF breakdown).
window.lineItemsForm = function (initialRows) {
    return {
        rows: initialRows.length ? initialRows : [],
    };
};

// Line-item typeahead backed by the Items library. `mode` decides what a pick
// fills in: 'create' (New Job rows: desc/price) or 'doc' (document modal rows:
// item/desc/price). Free typing still works for one-off items.
Alpine.data('itemCombo', (url, dept, mode) => ({
    open: false, results: [], loading: false, timer: null, seq: 0,
    search(q) {
        this.open = true;
        clearTimeout(this.timer);
        this.timer = setTimeout(async () => {
            const mine = ++this.seq;
            this.loading = true;
            try {
                const res = await fetch(`${url}?q=${encodeURIComponent((q || '').trim())}&dept=${encodeURIComponent(dept)}`, { headers: { Accept: 'application/json' } });
                if (mine === this.seq && res.ok) this.results = await res.json();
            } catch (e) { /* dropdown just stays as it was */ }
            if (mine === this.seq) this.loading = false;
        }, 200);
    },
    pick(row, r) {
        if ('item' in row) {
            row.item = r.name;
            row.desc = r.description || '';
        } else {
            row.desc = r.name;
        }
        if (r.price !== null) row.price = r.price;
        this.open = false;
    },
}));

// Malaysian postcode -> city/state auto-fill. Wired via onblur="lookupPostcode(this)"
// on a postcode <input> that sits in the same .grid container as the city/state
// inputs (see resources/views/customers/index.blade.php). Never overwrites what's
// already typed there — a postcode can span more than one town, and staff who
// already picked the right one shouldn't have it silently replaced.
window.lookupPostcode = async function (input) {
    const value = input.value.trim();
    if (!/^\d{5}$/.test(value)) return;
    const container = input.closest('.grid');
    if (!container) return;
    try {
        const res = await fetch(`/postcode-lookup/${value}`, { headers: { Accept: 'application/json' } });
        if (!res.ok) return;
        const data = await res.json();
        const cityInput = container.querySelector('[name="city"]');
        const stateInput = container.querySelector('[name="state"]');
        if (cityInput && !cityInput.value.trim()) cityInput.value = data.city;
        if (stateInput && !stateInput.value.trim()) stateInput.value = data.state;
    } catch (e) { /* leave fields as staff typed */ }
};

Alpine.start();
