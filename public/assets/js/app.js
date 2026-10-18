export async function api(path, options = {}) {
    const res = await fetch(path, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            ...(options.body ? { 'Content-Type': 'application/json' } : {}),
        },
        ...options,
    });

    if (res.status === 401) {
        window.location.assign('/login?redirect=' + encodeURIComponent(window.location.pathname));
        throw new Error('No autenticado');
    }

    return res.json();
}
