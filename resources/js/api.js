// Thin JSON client. Every error comes back as a list of human-readable messages.
async function request(path, options = {}) {
    let response;
    try {
        response = await fetch(`/api/${path}`, {
            ...options,
            headers: { Accept: 'application/json', ...(options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }), ...options.headers },
        });
    } catch {
        throw ['Network error. Check your connection and try again.'];
    }
    const body = await response.json().catch(() => ({}));
    if (!response.ok) {
        const messages = body.errors ? Object.values(body.errors).flat() : [body.message ?? 'Something went wrong.'];
        throw messages;
    }
    return body;
}

const post = (path, data, idempotencyKey) =>
    request(path, {
        method: 'POST',
        body: data instanceof FormData ? data : JSON.stringify(data),
        headers: idempotencyKey ? { 'Idempotency-Key': idempotencyKey } : {},
    });

export const api = {
    warehouses: () => request('warehouses'),
    products: () => request('products'),
    movements: () => request('movements'),
    imports: () => request('price-imports'),
    order: (data, key) => post('orders', data, key),
    transfer: (data, key) => post('transfers', data, key),
    importPrices: (file) => {
        const form = new FormData();
        form.append('file', file);
        return post('price-imports', form);
    },
};

export const euros = (cents) => new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'EUR' }).format(cents / 100);

export const newKey = () => crypto.randomUUID();
