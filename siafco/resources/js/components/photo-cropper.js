import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';

const MB = 1024 * 1024;

function fileFromBlob(blob) {
    return new File([blob], `profile-photo-${Date.now()}.jpg`, {
        type: 'image/jpeg',
        lastModified: Date.now(),
    });
}

async function compressLargeImage(file) {
    if (file.size <= 2 * MB) return file;

    const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
    const scale = Math.min(1, 2000 / Math.max(bitmap.width, bitmap.height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bitmap.width * scale);
    canvas.height = Math.round(bitmap.height * scale);
    const context = canvas.getContext('2d');
    context.imageSmoothingEnabled = true;
    context.imageSmoothingQuality = 'high';
    context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    bitmap.close();

    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.9));
    if (!blob) throw new Error('compression-failed');
    return fileFromBlob(blob);
}

export function initPhotoCropper(root) {
    const source = root.querySelector('[data-photo-source]');
    const output = root.querySelector('[data-photo-output]');
    const modal = root.querySelector('[data-crop-modal]');
    const cropImage = root.querySelector('[data-crop-image]');
    const preview = root.querySelector('[data-photo-preview-image]');
    const placeholder = root.querySelector('[data-photo-placeholder]');
    const status = root.querySelector('[data-photo-status]');
    const details = root.querySelector('[data-photo-details]');
    const edit = root.querySelector('[data-photo-edit]');
    const change = root.querySelector('[data-photo-change]');
    const remove = root.querySelector('[data-photo-remove]');
    const confirm = root.querySelector('[data-crop-confirm]');
    const zoom = root.querySelector('[data-crop-zoom]');
    const required = root.dataset.photoRequired === 'true';
    const initialSrc = root.dataset.photoInitial || '';
    const closeButtons = root.querySelectorAll('[data-crop-close], [data-crop-cancel]');
    let cropper;
    let sourceUrl;
    let previewUrl;
    let trigger;
    let baseRatio = 1;

    const setStatus = (message, error = false) => {
        status.textContent = message;
        status.classList.toggle('text-red-700', error);
        status.classList.toggle('font-bold', error);
    };

    const syncZoom = () => {
        if (!cropper || !zoom) return;
        cropper.zoomTo(baseRatio * Number(zoom.value));
    };

    const openModal = (url, opener) => {
        trigger = opener;
        cropImage.src = url;
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
        cropper?.destroy();
        cropper = new Cropper(cropImage, {
            aspectRatio: 1,
            viewMode: 1,
            dragMode: 'move',
            autoCropArea: 1,
            responsive: true,
            background: false,
            guides: true,
            center: true,
            highlight: false,
            cropBoxMovable: true,
            cropBoxResizable: true,
            toggleDragModeOnDblclick: false,
            movable: true,
            zoomable: true,
            rotatable: false,
            scalable: false,
            ready() {
                baseRatio = cropper.getImageData().ratio || 1;
                zoom.value = '1';
            },
        });
        modal.querySelector('[data-crop-close]').focus();
    };

    const closeModal = () => {
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
        cropper?.destroy();
        cropper = null;
        trigger?.focus();
    };

    source.addEventListener('change', async () => {
        const file = source.files?.[0];
        if (!file) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            source.value = '';
            setStatus('Selecciona una imagen JPG, JPEG, PNG o WEBP válida.', true);
            return;
        }

        setStatus('Preparando fotografía...');
        try {
            const prepared = await compressLargeImage(file);
            if (sourceUrl) URL.revokeObjectURL(sourceUrl);
            sourceUrl = URL.createObjectURL(prepared);
            openModal(sourceUrl, root.querySelector('[data-photo-select]'));
        } catch {
            source.value = '';
            setStatus('No fue posible procesar la fotografía seleccionada.', true);
        }
    });

    edit.addEventListener('click', () => sourceUrl && openModal(sourceUrl, edit));
    change.addEventListener('click', () => source.click());
    closeButtons.forEach((button) => button.addEventListener('click', closeModal));
    zoom?.addEventListener('input', syncZoom);
    root.querySelector('[data-crop-zoom-in]')?.addEventListener('click', () => {
        zoom.value = String(Math.min(Number(zoom.max), Number(zoom.value) + 0.1));
        syncZoom();
    });
    root.querySelector('[data-crop-zoom-out]')?.addEventListener('click', () => {
        zoom.value = String(Math.max(Number(zoom.min), Number(zoom.value) - 0.1));
        syncZoom();
    });
    root.querySelector('[data-crop-reset]')?.addEventListener('click', () => {
        cropper?.reset();
        baseRatio = cropper?.getImageData().ratio || 1;
        zoom.value = '1';
    });

    confirm.addEventListener('click', () => {
        if (!cropper) return;
        const canvas = cropper.getCroppedCanvas({
            width: 600,
            height: 600,
            fillColor: '#ffffff',
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high',
        });
        canvas.toBlob((blob) => {
            if (!blob) return setStatus('La fotografía no pudo procesarse.', true);
            const transfer = new DataTransfer();
            transfer.items.add(fileFromBlob(blob));
            output.files = transfer.files;
            output.setCustomValidity('');
            output.dispatchEvent(new Event('change', { bubbles: true }));

            if (previewUrl) URL.revokeObjectURL(previewUrl);
            previewUrl = URL.createObjectURL(blob);
            preview.src = previewUrl;
            preview.classList.remove('hidden');
            placeholder.classList.add('hidden');
            edit.classList.remove('hidden');
            change.classList.remove('hidden');
            remove.classList.remove('hidden');
            details.textContent = `Nueva fotografía seleccionada · 600 × 600 px · ${Math.max(1, Math.round(blob.size / 1024))} KB`;
            details.classList.remove('hidden');
            setStatus('Fotografía lista para guardar.');
            closeModal();
        }, 'image/jpeg', 0.9);
    });

    remove.addEventListener('click', () => {
        source.value = '';
        output.value = '';
        output.setCustomValidity(required ? 'Selecciona y recorta una fotografía.' : '');
        preview.src = initialSrc;
        preview.classList.toggle('hidden', !initialSrc);
        placeholder.classList.toggle('hidden', Boolean(initialSrc));
        edit.classList.add('hidden');
        change.classList.add('hidden');
        remove.classList.add('hidden');
        details.classList.add('hidden');
        setStatus('JPG, JPEG, PNG o WEBP. Tamaño máximo: 5 MB.');
        output.dispatchEvent(new Event('change', { bubbles: true }));
    });
    root.closest('form')?.addEventListener('reset', () => {
        if (output.files?.length) requestAnimationFrame(() => remove.click());
    });

    modal.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            closeModal();
            return;
        }
        if (event.key !== 'Tab') return;
        const focusable = [...modal.querySelectorAll('button:not([disabled]), input:not([disabled])')];
        const first = focusable[0];
        const last = focusable.at(-1);
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    output.setCustomValidity(required ? 'Selecciona y recorta una fotografía.' : '');
}

export function initPhotoCroppers(scope = document) {
    scope.querySelectorAll('[data-photo-cropper]').forEach(initPhotoCropper);
}
