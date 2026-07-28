import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';

const MB = 1024 * 1024;

function fileFromBlob(blob) {
    return new File([blob], `affiliate-photo-${Date.now()}.jpg`, { type: 'image/jpeg', lastModified: Date.now() });
}

async function compressLargeImage(file) {
    if (file.size <= 2 * MB) return file;

    const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
    const maxDimension = 2000;
    const scale = Math.min(1, maxDimension / Math.max(bitmap.width, bitmap.height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bitmap.width * scale);
    canvas.height = Math.round(bitmap.height * scale);
    const context = canvas.getContext('2d');
    context.imageSmoothingEnabled = true;
    context.imageSmoothingQuality = 'high';
    context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    bitmap.close();

    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.86));
    if (!blob) throw new Error('compression-failed');
    return fileFromBlob(blob);
}

function initPhotoCropper(root) {
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
    const closeButtons = root.querySelectorAll('[data-crop-close], [data-crop-cancel]');
    let cropper;
    let sourceUrl;
    let previewUrl;
    let trigger;

    const setStatus = (message, error = false) => {
        status.textContent = message;
        status.classList.toggle('text-red-700', error);
        status.classList.toggle('font-bold', error);
    };

    const openModal = (url, opener) => {
        trigger = opener;
        cropImage.src = url;
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        cropper?.destroy();
        cropper = new Cropper(cropImage, {
            aspectRatio: 1,
            viewMode: 1,
            autoCropArea: 1,
            responsive: true,
            background: false,
            movable: true,
            zoomable: true,
            rotatable: false,
            scalable: false,
        });
        modal.querySelector('[data-crop-close]').focus();
    };

    const closeModal = () => {
        modal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
        cropper?.destroy();
        cropper = null;
        trigger?.focus();
    };

    const processSelection = async () => {
        const file = source.files?.[0];
        if (!file) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            source.value = '';
            setStatus('No fue posible procesar la fotografía. Selecciona otra imagen en formato JPG, PNG o WEBP.', true);
            return;
        }

        setStatus('Preparando fotografía…');
        try {
            const prepared = await compressLargeImage(file);
            if (sourceUrl) URL.revokeObjectURL(sourceUrl);
            sourceUrl = URL.createObjectURL(prepared);
            openModal(sourceUrl, root.querySelector('[data-photo-select]'));
        } catch {
            source.value = '';
            setStatus('No fue posible procesar la fotografía. Selecciona otra imagen en formato JPG, PNG o WEBP.', true);
        }
    };

    source.addEventListener('change', processSelection);
    edit.addEventListener('click', () => sourceUrl && openModal(sourceUrl, edit));
    change.addEventListener('click', () => source.click());
    closeButtons.forEach((button) => button.addEventListener('click', closeModal));

    confirm.addEventListener('click', () => {
        if (!cropper) return;
        setStatus('Preparando fotografía…');
        const canvas = cropper.getCroppedCanvas({
            width: 600,
            height: 600,
            fillColor: '#ffffff',
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high',
        });
        canvas.toBlob((blob) => {
            if (!blob) {
                setStatus('La fotografía no pudo procesarse.', true);
                return;
            }
            const file = fileFromBlob(blob);
            const transfer = new DataTransfer();
            transfer.items.add(file);
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
            details.textContent = `Fotografía lista · 600 × 600 px · ${Math.max(1, Math.round(blob.size / 1024))} KB`;
            details.classList.remove('hidden');
            setStatus('Fotografía lista para la credencial.');
            closeModal();
        }, 'image/jpeg', 0.86);
    });

    remove.addEventListener('click', () => {
        source.value = '';
        output.value = '';
        output.setCustomValidity('Selecciona y recorta una fotografía.');
        preview.removeAttribute('src');
        preview.classList.add('hidden');
        placeholder.classList.remove('hidden');
        edit.classList.add('hidden');
        change.classList.add('hidden');
        remove.classList.add('hidden');
        details.classList.add('hidden');
        setStatus('JPG, PNG o WEBP. La imagen final será cuadrada.');
        output.dispatchEvent(new Event('change', { bubbles: true }));
    });

    modal.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            closeModal();
            return;
        }
        if (event.key !== 'Tab') return;
        const focusable = [...modal.querySelectorAll('button:not([disabled]), [href], input:not([disabled])')];
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

    output.setCustomValidity('Selecciona y recorta una fotografía.');
}

function validationMessage(field) {
    if (field.name === 'photo') return 'Selecciona y recorta una fotografía.';
    if (field.name === 'email' && field.validity.typeMismatch) return 'Ingresa un correo electrónico válido.';
    if (field.name === 'phone') return 'Ingresa un número de celular válido.';
    if (field.name === 'password_confirmation') return 'Las contraseñas no coinciden.';
    if (field.tagName === 'SELECT') return 'Selecciona una opción.';
    return 'Este campo es obligatorio.';
}

function validateField(field, force = false) {
    if (!force && field.dataset.touched !== 'true') return true;
    const form = field.form;
    if (field.name === 'password_confirmation') {
        field.setCustomValidity(field.value !== form.elements.password.value ? 'Las contraseñas no coinciden.' : '');
    }
    const valid = field.checkValidity();
    const wrapper = field.closest('[data-field-wrapper]') ?? field.parentElement;
    const visualField = field.name === 'photo' ? wrapper.querySelector('[data-photo-box]') : field;
    const label = wrapper.querySelector('[data-field-label]');
    const error = wrapper.querySelector(`[data-field-error="${field.name}"]`);

    field.setAttribute('aria-invalid', valid ? 'false' : 'true');
    visualField?.classList.toggle('border-red-500', !valid);
    visualField?.classList.toggle('bg-red-50', !valid);
    label?.classList.toggle('text-red-700', !valid);
    if (error) {
        error.textContent = valid ? '' : validationMessage(field);
        error.classList.toggle('hidden', valid);
    }
    return valid;
}

function initForm(form) {
    const fields = [...form.querySelectorAll('[data-validate-field]')];
    const summary = form.querySelector('[data-validation-summary]');
    fields.forEach((field) => {
        const eventName = field.tagName === 'SELECT' || field.type === 'file' ? 'change' : 'blur';
        field.addEventListener(eventName, () => {
            field.dataset.touched = 'true';
            validateField(field, true);
        });
        field.addEventListener('input', () => field.dataset.touched === 'true' && validateField(field, true));
    });

    form.addEventListener('submit', (event) => {
        fields.forEach((field) => field.dataset.touched = 'true');
        const invalid = fields.filter((field) => !validateField(field, true));
        if (!invalid.length) return;

        event.preventDefault();
        summary?.classList.remove('hidden');
        invalid[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        window.setTimeout(() => invalid[0].focus(), 350);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-photo-cropper]').forEach(initPhotoCropper);
    document.querySelectorAll('[data-public-affiliation-form]').forEach(initForm);
});
