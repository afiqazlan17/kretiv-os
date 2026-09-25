// Renders a PDF blob URL page-by-page onto a <canvas>, with our own
// Prev/Next controls. Mobile browsers' native embedded PDF viewer (used
// when a PDF is just put in an <iframe src="...">) doesn't reliably
// support paging or vertical scrolling across pages inside the frame —
// #toolbar=0/#navpanes=0/#view=Fit fragment hints are Adobe/Chrome
// conventions that iOS/Android's own viewers mostly ignore. Rendering via
// pdf.js and drawing to a canvas ourselves sidesteps that entirely.
//
// pdf.js itself is ~1.2MB, so it's dynamically imported on first use
// instead of bundled into app.js — pages that never open a document
// preview (the vast majority of the app) shouldn't pay for it.
let pdfjsLibPromise = null;
function loadPdfjs() {
    if (!pdfjsLibPromise) {
        pdfjsLibPromise = Promise.all([
            import('pdfjs-dist'),
            import('pdfjs-dist/build/pdf.worker.min.mjs?url'),
        ]).then(([pdfjsLib, workerUrl]) => {
            pdfjsLib.GlobalWorkerOptions.workerSrc = workerUrl.default;
            return pdfjsLib;
        });
    }
    return pdfjsLibPromise;
}

export function createPdfPager() {
    return {
        pdfDoc: null,
        pageNum: 1,
        numPages: 1,
        canvasBusy: false,
        error: '',
        // pdf.js only allows one render task per canvas at a time — the
        // automatic render kicked off by load() and a re-render fired by
        // switching to the Preview tab can easily overlap (both start
        // before either has finished its own `await`s), and a bare
        // cancel-the-previous-task guard has a race window right at the
        // start of render() where two calls can both see "no task running
        // yet" and both proceed. That collision throws deep inside pdf.js
        // ("Cannot read private member #n from an object whose class did
        // not declare it") and leaves the canvas blank. Chaining every
        // render() call onto this promise serializes them completely —
        // each one only starts once the previous has fully finished — so
        // there's no window for two to overlap on the same canvas.
        _queue: Promise.resolve(),

        async load(blobUrl, canvas) {
            this.canvasBusy = true;
            this.error = '';
            try {
                const pdfjsLib = await loadPdfjs();
                this.pdfDoc = await pdfjsLib.getDocument({ url: blobUrl }).promise;
                this.numPages = this.pdfDoc.numPages;
                this.pageNum = 1;
                await this.render(canvas);
            } catch (e) {
                console.error('pdf-pager: failed to render preview', e);
                this.error = 'Preview unavailable on this device — use Download PDF below instead.';
            } finally {
                this.canvasBusy = false;
            }
        },

        render(canvas) {
            const run = this._queue.then(() => this._renderNow(canvas)).catch((e) => {
                console.error('pdf-pager: render failed', e);
                this.error = 'Preview unavailable on this device — use Download PDF below instead.';
            });
            this._queue = run;
            return run;
        },

        async _renderNow(canvas) {
            if (!this.pdfDoc || !canvas) return;
            const page = await this.pdfDoc.getPage(this.pageNum);
            const width = canvas.parentElement?.clientWidth || 600;
            const scale = Math.min(2, width / page.getViewport({ scale: 1 }).width);
            const viewport = page.getViewport({ scale });
            const dpr = window.devicePixelRatio || 1;
            canvas.width = viewport.width * dpr;
            canvas.height = viewport.height * dpr;
            canvas.style.width = viewport.width + 'px';
            canvas.style.height = viewport.height + 'px';
            const ctx = canvas.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            await page.render({ canvasContext: ctx, viewport }).promise;
        },

        async next(canvas) {
            if (this.pageNum >= this.numPages) return;
            this.pageNum++;
            await this.render(canvas);
        },

        async prev(canvas) {
            if (this.pageNum <= 1) return;
            this.pageNum--;
            await this.render(canvas);
        },

        reset() {
            this.pdfDoc = null;
            this.pageNum = 1;
            this.numPages = 1;
            this.error = '';
        },
    };
}
