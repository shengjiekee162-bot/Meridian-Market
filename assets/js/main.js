document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.toast-card').forEach((toast) => {
        setTimeout(() => toast.remove(), 4500);
    });

    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
                event.preventDefault();
                return;
            }
            const button = form.querySelector('[type="submit"]');
            if (button && !event.defaultPrevented) {
                setTimeout(() => {
                    button.disabled = true;
                }, 0);
            }
        });
    });

    document.querySelectorAll('[data-qty]').forEach((wrap) => {
        const input = wrap.querySelector('input');
        const min = Number(input.min || 1);
        const max = Number(input.max || 20);
        wrap.querySelector('[data-qty-minus]')?.addEventListener('click', () => {
            input.value = String(Math.max(min, Number(input.value || min) - 1));
        });
        wrap.querySelector('[data-qty-plus]')?.addEventListener('click', () => {
            input.value = String(Math.min(max, Number(input.value || min) + 1));
        });
    });

    const main = document.querySelector('[data-gallery-main]');
    document.querySelectorAll('[data-gallery-src]').forEach((button) => {
        button.addEventListener('click', () => {
            if (main) {
                main.src = button.dataset.gallerySrc;
            }
        });
    });

    const reveals = document.querySelectorAll('.reveal');
    if ('IntersectionObserver' in window && reveals.length) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });
        reveals.forEach((node) => observer.observe(node));
    } else {
        reveals.forEach((node) => node.classList.add('is-visible'));
    }
});
