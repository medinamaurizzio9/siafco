(() => {
    // Revalidate the badge and the dismissal when the browser restores a cached page.
    window.addEventListener('pageshow', event => {
        if (event.persisted) window.location.reload();
    });

    const dialog = document.querySelector('.communication-dialog');
    if (!dialog) return;
    dialog.showModal();
    dialog.addEventListener('cancel', event => {
        event.preventDefault();
        dialog.querySelector('form').requestSubmit();
    });
})();
