(() => {
    'use strict';
    const dialog = document.querySelector('[data-image-dialog]');
    if (!dialog) return;
    const image = dialog.querySelector('[data-image-full]');
    const urls = new WeakMap();
    const liveUrls = new Set();
    function release(input) {
        (urls.get(input) || []).forEach(url => { URL.revokeObjectURL(url); liveUrls.delete(url); });
        urls.delete(input);
    }
    function render(input) {
        release(input);
        const field = input.closest('.upload-field');
        if (!field) return;
        const container = field.querySelector('[data-file-previews]');
        container.replaceChildren();
        const created = [];
        for (const file of input.files) {
            const card = document.createElement('div');
            card.className = 'file-preview';
            const caption = document.createElement('span');
            caption.textContent = `${file.name} · ${(file.size / 1024 / 1024).toFixed(2)} MB`;
            if (['image/jpeg', 'image/png', 'image/webp'].includes(file.type) && file.size <= 10 * 1024 * 1024) {
                const url = URL.createObjectURL(file);
                created.push(url);
                liveUrls.add(url);
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'image-thumbnail';
                button.dataset.imagePreview = url;
                button.dataset.imageName = file.name;
                button.setAttribute('aria-label', `Ampliar ${file.name}`);
                const thumbnail = document.createElement('img');
                thumbnail.src = url;
                thumbnail.alt = `Vista previa de ${file.name}`;
                thumbnail.loading = 'lazy';
                thumbnail.decoding = 'async';
                button.append(thumbnail);
                card.append(button);
            }
            card.append(caption);
            container.append(card);
        }
        urls.set(input, created);
        field.querySelector('[data-clear-files]').hidden = input.files.length === 0;
    }
    document.addEventListener('change', event => {
        if (event.target.matches('[data-preview-input]')) render(event.target);
    });
    document.addEventListener('click', event => {
        const clear = event.target.closest('[data-clear-files]');
        if (clear) {
            const input = clear.closest('.upload-field').querySelector('[data-preview-input]');
            input.value = '';
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
        const button = event.target.closest('[data-image-preview]');
        if (button) {
            image.src = button.dataset.imagePreview;
            image.alt = button.dataset.imageName || 'Documento privado';
            dialog.querySelector('[data-image-caption]').textContent = image.alt;
            if (!dialog.open) dialog.showModal();
        }
        if (event.target.closest('[data-close-image]')) dialog.close();
    });
    dialog.addEventListener('close', () => { image.removeAttribute('src'); image.alt = ''; });
    window.addEventListener('pagehide', () => { liveUrls.forEach(url => URL.revokeObjectURL(url)); liveUrls.clear(); });
    window.ATDPreviews = { releaseTree: element => element.querySelectorAll('[data-preview-input]').forEach(release) };
})();
