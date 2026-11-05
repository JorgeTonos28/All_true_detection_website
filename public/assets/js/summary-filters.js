(() => {
    const dialog = document.getElementById('summary-filters');
    const trigger = document.querySelector('[data-open-summary-filters]');
    if (!dialog || !trigger) return;
    trigger.addEventListener('click', () => dialog.showModal());
    dialog.querySelector('[data-close-summary-filters]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => trigger.focus());
    if (dialog.hasAttribute('data-filter-errors')) dialog.showModal();
})();
