document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-share-link]');
    if (!button) return;

    const shareData = {
        title: button.dataset.shareTitle,
        text: button.dataset.shareText,
        url: button.dataset.shareLink,
    };
    const feedback = button.closest('.crm-capture-qr-card')?.querySelector('[data-share-feedback]');

    try {
        if (navigator.share) {
            await navigator.share(shareData);
            if (feedback) feedback.textContent = 'Enlace compartido.';
            return;
        }

        await navigator.clipboard.writeText(shareData.url);
        if (feedback) feedback.textContent = 'Enlace copiado.';
    } catch (error) {
        if (error?.name !== 'AbortError' && feedback) {
            feedback.textContent = 'No pudimos compartir el enlace.';
        }
    }
});
