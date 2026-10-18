document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('register-form');
    if (!form) {
        return;
    }

    const error = document.getElementById('register-error');
    const termsDialog = document.getElementById('terms-dialog');
    const termsLink = document.getElementById('open-terms');
    const closeTerms = document.getElementById('close-terms');
    const fields = {
        name: (value) => (
            value.trim().length >= 2 && value.trim().length <= 100
                ? true
                : 'Escribe tu nombre (entre 2 y 100 caracteres).'
        ),
        email: (value) => (
            /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)
                ? true
                : 'Correo con formato inválido.'
        ),
        password: (value) => (
            value.length >= 10 && value.length <= 128
                && /[a-z]/.test(value) && /[A-Z]/.test(value) && /\d/.test(value)
                ? true
                : 'Usa entre 10 y 128 caracteres, con mayúscula, minúscula y número.'
        ),
    };

    const validateField = (name) => {
        const input = form.elements[name];
        const result = fields[name](input.value);
        input.setCustomValidity(result === true ? '' : result);

        const message = document.getElementById(name + '-msg');
        if (message) {
            message.textContent = result === true ? '' : result;
        }

        return result === true && input.checkValidity();
    };

    const terms = form.elements.terms;
    const validateTerms = () => {
        const message = document.getElementById('terms-msg');
        const text = terms.checked ? '' : 'Debes aceptar los términos para continuar.';
        terms.setCustomValidity(text);
        if (message) {
            message.textContent = text;
        }
        return terms.checked;
    };

    for (const name of Object.keys(fields)) {
        form.elements[name].addEventListener('input', () => validateField(name));
    }
    terms.addEventListener('change', validateTerms);

    termsLink.addEventListener('click', (event) => {
        event.preventDefault();
        termsDialog.showModal();
    });
    closeTerms.addEventListener('click', () => termsDialog.close());

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const validFields = Object.keys(fields).map(validateField).every(Boolean);
        const validTerms = validateTerms();
        if (!validFields || !validTerms || !form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        error.hidden = true;
        error.textContent = '';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    name: form.elements.name.value,
                    email: form.elements.email.value,
                    password: form.elements.password.value,
                    terms: terms.checked,
                }),
            });
            const payload = await response.json();

            if (!response.ok || payload.status !== 'success') {
                throw new Error(payload.message || 'No se pudo crear la cuenta.');
            }

            window.location.assign('/login');
        } catch (requestError) {
            error.textContent = requestError instanceof Error
                ? requestError.message
                : 'No se pudo crear la cuenta.';
            error.hidden = false;
        } finally {
            button.disabled = false;
        }
    });
});
