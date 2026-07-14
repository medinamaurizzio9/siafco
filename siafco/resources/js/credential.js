import html2canvas from 'html2canvas';

document.addEventListener('DOMContentLoaded', () => {
    const button = document.getElementById('download-credential-png');
    const card = document.getElementById('credential-card');

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
