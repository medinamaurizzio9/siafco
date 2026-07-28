import html2canvas from 'html2canvas';

document.addEventListener('DOMContentLoaded', () => {
    const button = document.getElementById('download-credential-png');
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

    if (!button || !card) {
        return;
    }

    button.addEventListener('click', async () => {
        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = 'Generando...';

        try {
            const canvas = await html2canvas(card, {
                scale: 3,
                useCORS: true,
                backgroundColor: '#ffffff',
                logging: false,
                onclone: (documentClone) => {
                    const clonedCard = documentClone.getElementById('credential-card');
                    const clonedCanvas = documentClone.getElementById('credential-canvas');
                    clonedCard?.style.setProperty('--credential-scale', '1');
                    if (clonedCanvas) {
                        clonedCanvas.style.width = '850px';
                        clonedCanvas.style.height = '540px';
                    }

                    documentClone
                        .querySelectorAll('link[rel="stylesheet"]')
                        .forEach((link) => {
                            if (!link.href.includes('/assets/credential-')) {
                                link.remove();
                            }
                        });

                    documentClone.body.style.backgroundColor = '#ffffff';
                },
            });

            const link = document.createElement('a');
            link.download = button.dataset.filename || 'credencial.png';
            link.href = canvas.toDataURL('image/png', 1);
            link.click();
        } catch (error) {
            console.error(error);
            alert('No se pudo generar la credencial en PNG.');
        } finally {
            button.disabled = false;
            button.textContent = originalText;
        }
    });
});
