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

        async load(blobUrl, canvas) {
            this.canvasBusy = true;
            try {
                const pdfjsLib = await loadPdfjs();
                this.pdfDoc = await pdfjsLib.getDocument(blobUrl).promise;
                this.numPages = this.pdfDoc.numPages;
                this.pageNum = 1;
                await this.render(canvas);
            } finally {
                this.canvasBusy = false;
            }
        },

        async render(canvas) {
            if (!this.pdfDoc || !canvas) return;
            const page = await this.pdfDoc.getPage(this.pageNum);
            const scale = Math.min(2, (canvas.parentElement?.clientWidth || 600) / page.getViewport({ scale: 1 }).width);
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
        },
    };
}
