document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('paypalHandoff');
    if (form) {
        // Small delay so the page paints, then leave for PayPal (bank-style handoff).
        window.setTimeout(() => {
            form.submit();
        }, 150);
        return;
    }
    const link = document.querySelector('[data-paypal-continue]');
    if (link && link.getAttribute('href')) {
        window.location.replace(link.getAttribute('href'));
    }
});
