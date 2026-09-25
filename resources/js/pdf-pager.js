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

// createPdfPager() is called from inside an Alpine x-data() object, so
// whatever it returns gets deep-wrapped in Alpine's (@vue/reactivity)
// Proxy. pdf.js's PDFDocumentProxy/PDFPageProxy instances use real ES
// private class fields (#foo) internally — a Proxy standing in for `this`
// breaks that private-field brand check with "Cannot read private member
// #n from an object whose class did not declare it" the moment any of
// pdf.js's own methods run against the proxied instance. Keeping pdfDoc
// (and the in-flight render queue) in a closure instead of as a property
// on the returned object means Alpine never sees or wraps them — they're
// plain, un-proxied references from pdf.js's point of view.
export function createPdfPager() {
    let pdfDoc = null;
    let queue = Promise.resolve();

    async function renderNow(canvas, pageNum) {
        if (!pdfDoc || !canvas) return;
        const page = await pdfDoc.getPage(pageNum);
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
    }

    return {
        // Only plain primitives live here — safe for Alpine to make
        // reactive, since the UI (page counter, Prev/Next disabled state,
        // error message) binds directly to these.
        pageNum: 1,
        numPages: 1,
        canvasBusy: false,
        error: '',

        async load(blobUrl, canvas) {
            this.canvasBusy = true;
            this.error = '';
            try {
                const pdfjsLib = await loadPdfjs();
                pdfDoc = await pdfjsLib.getDocument({ url: blobUrl }).promise;
                this.numPages = pdfDoc.numPages;
                this.pageNum = 1;
                await this.render(canvas);
            } catch (e) {
                console.error('pdf-pager: failed to load preview', e);
                this.error = 'Preview unavailable on this device — use Download PDF below instead.';
            } finally {
                this.canvasBusy = false;
            }
        },

        // Every render() call is chained onto the previous one so two
        // render tasks never touch the same canvas concurrently — pdf.js
        // only tolerates one render task per canvas at a time.
        render(canvas) {
            const pageNum = this.pageNum;
            const run = queue.then(() => renderNow(canvas, pageNum)).catch((e) => {
                console.error('pdf-pager: render failed', e);
                this.error = 'Preview unavailable on this device — use Download PDF below instead.';
            });
            queue = run;
            return run;
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
            pdfDoc = null;
            queue = Promise.resolve();
            this.pageNum = 1;
            this.numPages = 1;
            this.error = '';
        },
    };
}
