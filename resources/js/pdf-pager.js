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
        renderTask: null,

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

        async render(canvas) {
            if (!this.pdfDoc || !canvas) return;
            // pdf.js only allows one render task per canvas at a time — the
            // automatic render from load() and a re-render triggered by
            // switching to the Preview tab (before the first one finished)
            // could otherwise collide and throw deep inside pdf.js's
            // internals ("Cannot read private member ... from an object
            // whose class did not declare it").
            if (this.renderTask) {
                this.renderTask.cancel();
                this.renderTask = null;
            }
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
            const task = page.render({ canvasContext: ctx, viewport });
            this.renderTask = task;
            try {
                await task.promise;
            } catch (e) {
                if (e?.name === 'RenderingCancelledException') return;
                throw e;
            } finally {
                if (this.renderTask === task) this.renderTask = null;
            }
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
