document.addEventListener('DOMContentLoaded', () => {
    const card = document.getElementById('credential-card');
    const canvas = document.getElementById('credential-canvas');

    const fitCredential = () => {
        if (!card || !canvas) {
            return;
        }

        card.style.setProperty('--credential-scale', String(canvas.clientWidth / 850));
    };

    fitCredential();

    if (canvas && 'ResizeObserver' in window) {
        new ResizeObserver(fitCredential).observe(canvas);
    } else {
        window.addEventListener('resize', fitCredential);
    }
});
