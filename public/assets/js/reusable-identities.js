(() => {
    'use strict';
    document.querySelectorAll('[data-identity-open]').forEach(button => {
        const dialog = document.getElementById(button.dataset.identityOpen);
        if (!(dialog instanceof HTMLDialogElement)) return;
        button.addEventListener('click', () => dialog.showModal());
        dialog.querySelector('[data-identity-close]').addEventListener('click', () => dialog.close());
        dialog.addEventListener('close', () => button.focus());
    });
})();
