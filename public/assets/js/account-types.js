(() => {
    const sync = form => {
        const type = form.querySelector('[data-account-type]');
        if (!type) return;
        const role = form.querySelector('[name="role"]');
        const existing = form.querySelector('[data-account-select]');
        const needsType = (!role || role.value === 'client') && (!existing || !existing.value);
        type.disabled = !needsType;
        type.required = needsType;
        const group = form.querySelector('[data-new-account-fields]');
        if (group) { group.hidden = !needsType; group.disabled = !needsType; }
        form.querySelectorAll('[data-company-only]').forEach(label => {
            const enabled = needsType && type.value === 'business';
            label.hidden = !enabled;
            label.querySelectorAll('input').forEach(input => { input.disabled = !enabled; input.required = enabled; });
        });
        const nameLabel = form.querySelector('[data-account-name-label]');
        if (nameLabel) nameLabel.textContent = type.value === 'business' ? 'Nombre o razón social de la empresa' : 'Nombre del titular de la cuenta';
    };
    document.querySelectorAll('form[data-account-type-form]').forEach(sync);
    document.addEventListener('change', event => {
        const form = event.target.closest('form[data-account-type-form]');
        if (form) sync(form);
    });
})();
