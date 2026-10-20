/**
 * Draw-to-sign pad for the staff handover form. The signature is posted as a
 * PNG data URL in a hidden field; the server decodes and checks it again.
 */
function initSignaturePads() {
    document.querySelectorAll('[data-signature-pad]').forEach((root) => {
        const canvas = root.querySelector('canvas');
        const field = root.querySelector('input[type="hidden"]');
        const clearButton = root.querySelector('[data-signature-clear]');
        const context = canvas.getContext('2d');
        let drawing = false;
        let dirty = false;

        const resize = () => {
            const ratio = window.devicePixelRatio || 1;
            const { width, height } = canvas.getBoundingClientRect();
            canvas.width = width * ratio;
            canvas.height = height * ratio;
            context.scale(ratio, ratio);
            context.lineWidth = 2;
            context.lineCap = 'round';
            context.strokeStyle = '#0f172a';
            field.value = '';
            dirty = false;
        };

        const point = (event) => {
            const rect = canvas.getBoundingClientRect();
            return { x: event.clientX - rect.left, y: event.clientY - rect.top };
        };

        canvas.addEventListener('pointerdown', (event) => {
            drawing = true;
            canvas.setPointerCapture(event.pointerId);
            const { x, y } = point(event);
            context.beginPath();
            context.moveTo(x, y);
        });
        canvas.addEventListener('pointermove', (event) => {
            if (!drawing) return;
            const { x, y } = point(event);
            context.lineTo(x, y);
            context.stroke();
            dirty = true;
        });
        const stop = () => {
            if (!drawing) return;
            drawing = false;
            if (dirty) field.value = canvas.toDataURL('image/png');
        };
        canvas.addEventListener('pointerup', stop);
        canvas.addEventListener('pointercancel', stop);
        clearButton?.addEventListener('click', resize);

        resize();
    });
}

initSignaturePads();
