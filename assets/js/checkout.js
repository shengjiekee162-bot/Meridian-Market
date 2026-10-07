document.addEventListener('DOMContentLoaded', () => {
    const methods = document.querySelectorAll('[data-pay]');
    const submit = document.querySelector('[data-checkout-submit]');

    function syncPaymentUi() {
        const selected = document.querySelector('[data-pay]:checked');
        const value = selected ? selected.value : '';
        document.querySelectorAll('[data-pay-panel]').forEach((panel) => {
            panel.hidden = panel.dataset.payPanel !== value;
        });
        if (submit) {
            const paypalLabel = submit.getAttribute('data-label-paypal') || 'Continue to PayPal';
            const otherLabel = submit.getAttribute('data-label-other') || 'Place order';
            submit.textContent = value === 'paypal' ? paypalLabel : otherLabel;
        }
    }

    methods.forEach((method) => {
        method.addEventListener('change', syncPaymentUi);
    });
    syncPaymentUi();
});
