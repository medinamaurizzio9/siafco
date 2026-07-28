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
    document.querySelectorAll('[data-public-affiliation-form]').forEach(initForm);
});
