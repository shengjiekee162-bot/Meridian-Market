document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-status-select]').forEach((select) => {
        const note = document.querySelector('[data-status-note]');
        const sync = () => {
            if (note) {
                note.required = select.value === 'cancelled';
            }
        };
        select.addEventListener('change', sync);
        sync();
    });
});
