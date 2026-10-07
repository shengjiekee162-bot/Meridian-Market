document.addEventListener('DOMContentLoaded', () => {
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const toastStack = document.querySelector('.toast-stack') || document.body;

    function csrfToken() {
        return csrfMeta?.getAttribute('content') || document.querySelector('input[name="csrf_token"]')?.value || '';
    }

    function showToast(message, type = 'success') {
        const card = document.createElement('div');
        card.className = `toast-card toast-${type}`;
        card.textContent = message;
        toastStack.appendChild(card);
        setTimeout(() => card.remove(), 4200);
    }

    function updateCartCount(count) {
        const badge = document.querySelector('[data-cart-count]');
        if (!badge) {
            return;
        }
        const value = Number(count || 0);
        badge.textContent = value > 0 ? String(value) : '';
        badge.classList.toggle('is-empty', value < 1);
        badge.classList.add('is-pop');
        setTimeout(() => badge.classList.remove('is-pop'), 420);
    }

    function applyCartTotals(cart) {
        if (!cart) {
            return;
        }
        const subtotal = document.querySelector('[data-cart-subtotal]');
        const discount = document.querySelector('[data-cart-discount]');
        const shipping = document.querySelector('[data-cart-shipping]');
        const total = document.querySelector('[data-cart-total]');
        if (subtotal) subtotal.textContent = cart.subtotal;
        if (discount) discount.textContent = `-${cart.discount}`;
        if (shipping) shipping.textContent = cart.shipping;
        if (total) total.textContent = cart.total;
    }

    async function postCart(form, extra = {}) {
        const body = new FormData(form);
        if (!body.get('csrf_token')) {
            body.set('csrf_token', csrfToken());
        }
        Object.entries(extra).forEach(([key, value]) => {
            body.set(key, value);
        });
        const response = await fetch(form.getAttribute('action') || window.location.href, {
            method: 'POST',
            body,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });
        const data = await response.json().catch(() => null);
        if (!response.ok || !data || !data.ok) {
            throw new Error((data && data.message) || 'The cart could not be updated.');
        }
        return data;
    }

    document.querySelectorAll('form[data-add-cart]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = form.querySelector('[type="submit"]');
            if (button) {
                button.disabled = true;
            }
            try {
                const data = await postCart(form);
                updateCartCount(data.cart_count);
                showToast(data.message || 'Added to cart.');
            } catch (error) {
                showToast(error.message || 'Could not add to cart.', 'error');
            } finally {
                if (button) {
                    button.disabled = false;
                }
            }
        });
    });

    document.querySelectorAll('form[data-product-cart]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            const submitter = event.submitter;
            if (!submitter || submitter.getAttribute('value') !== 'add') {
                return;
            }
            event.preventDefault();
            submitter.disabled = true;
            try {
                const data = await postCart(form, { action: 'add' });
                updateCartCount(data.cart_count);
                showToast(data.message || 'Added to cart.');
            } catch (error) {
                showToast(error.message || 'Could not add to cart.', 'error');
            } finally {
                submitter.disabled = false;
            }
        });
    });

    document.querySelectorAll('[data-line-qty]').forEach((input) => {
        input.addEventListener('change', () => {
            const max = Number(input.max || 20);
            const min = Number(input.min || 1);
            let value = Number(input.value || min);
            if (Number.isNaN(value)) {
                value = min;
            }
            input.value = String(Math.min(max, Math.max(min, value)));
        });
    });

    document.querySelectorAll('form[data-cart-update]').forEach((form) => {
        const qtyWrap = form.querySelector('[data-qty]');
        const input = form.querySelector('[data-line-qty]');
        let busy = false;
        const sync = async () => {
            if (busy) {
                return;
            }
            busy = true;
            try {
                const data = await postCart(form);
                updateCartCount(data.cart_count);
                applyCartTotals(data.cart);
                if (data.lines) {
                    Object.entries(data.lines).forEach(([itemId, total]) => {
                        const line = document.querySelector(`[data-cart-line][data-item-id="${itemId}"]`);
                        const target = line?.querySelector('[data-line-total]');
                        if (target) {
                            target.textContent = total;
                        }
                    });
                }
                showToast(data.message || 'Cart updated.');
            } catch (error) {
                showToast(error.message || 'Could not update cart.', 'error');
            } finally {
                busy = false;
            }
        };
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            sync();
        });
        qtyWrap?.querySelector('[data-qty-minus]')?.addEventListener('click', () => {
            window.setTimeout(sync, 10);
        });
        qtyWrap?.querySelector('[data-qty-plus]')?.addEventListener('click', () => {
            window.setTimeout(sync, 10);
        });
        input?.addEventListener('change', () => sync());
    });

    document.querySelectorAll('form[data-cart-remove]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            try {
                const data = await postCart(form);
                updateCartCount(data.cart_count);
                form.closest('[data-cart-line]')?.remove();
                showToast(data.message || 'Item removed.');
                if (Number(data.cart_count || 0) < 1 || !document.querySelector('[data-cart-line]')) {
                    window.location.reload();
                }
            } catch (error) {
                showToast(error.message || 'Could not remove item.', 'error');
            }
        });
    });
});
