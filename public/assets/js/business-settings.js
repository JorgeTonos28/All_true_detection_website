document.querySelectorAll('[data-bank-editor]').forEach(editor => {
    const rows = editor.querySelector('[data-bank-rows]');
    const template = editor.querySelector('[data-bank-template]');
    const empty = editor.querySelector('[data-bank-empty]');
    const feedback = editor.querySelector('[data-bank-feedback]');
    const refresh = () => {
        const accounts = [...rows.querySelectorAll('[data-bank-row]')];
        accounts.forEach((row, index) => row.querySelectorAll('[name]').forEach(input => {
            input.name = input.name.replace(/^bank_accounts\[[^\]]+\]/, `bank_accounts[${index}]`);
        }));
        empty.hidden = accounts.length > 0;
        editor.querySelector('[data-add-bank]').disabled = accounts.length >= 20;
    };
    editor.querySelector('[data-add-bank]').addEventListener('click', () => {
        if (rows.querySelectorAll('[data-bank-row]').length >= 20) return;
        rows.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(rows.children.length)));
        refresh();
        rows.lastElementChild.querySelector('input:not([type="hidden"])')?.focus();
        feedback.textContent = 'Cuenta agregada. Complete sus datos antes de guardar.';
    });
    rows.addEventListener('click', event => {
        const remove = event.target.closest('[data-remove-bank]');
        if (!remove) return;
        remove.closest('[data-bank-row]').remove();
        refresh();
        feedback.textContent = 'Cuenta retirada del formulario. Guarde para confirmar los cambios.';
    });
    refresh();
});
