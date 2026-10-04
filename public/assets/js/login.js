document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('login-form');
    if (!form) {
        return;
    }

    const error = document.getElementById('login-error');
    const button = form.querySelector('button[type="submit"]');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!button || !error) {
            return;
        }

        button.disabled = true;
        error.hidden = true;
        error.textContent = '';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    email: form.elements.email.value,
                    password: form.elements.password.value
                })
            });

            const payload = await response.json();

            if (!response.ok || payload.status !== 'success') {
                throw new Error(payload.message || 'No se pudo iniciar sesión.');
            }

            const redirectField = form.elements.redirect;
            if (redirectField && typeof redirectField.value === 'string' && redirectField.value.length > 0) {
                window.location.assign(redirectField.value);
                return;
            }

            window.location.assign('/dashboard');
        } catch (requestError) {
            const message = requestError instanceof Error ? requestError.message : 'No se pudo iniciar sesión.';
            error.textContent = message;
            error.hidden = false;
        } finally {
            button.disabled = false;
        }
    });
});
