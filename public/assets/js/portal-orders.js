(() => {
    'use strict';
    const form = document.querySelector('[data-order-compose]');
    if (!form) return;
    const picker = form.querySelector('[data-service-picker]');
    const details = picker.querySelector('details');
    const choices = Array.from(picker.querySelectorAll('input[type=checkbox]'));
    const selected = picker.querySelector('[data-selected-services]');
    function updateServices() {
        const checked = choices.filter(input => input.checked);
        picker.querySelector('[data-service-label]').textContent = checked.length
            ? `${checked.length} ${checked.length === 1 ? 'servicio seleccionado' : 'servicios seleccionados'}` : 'Seleccionar servicios';
        selected.replaceChildren();
        for (const input of checked) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'service-chip';
            button.textContent = `${input.dataset.label} ×`;
            button.setAttribute('aria-label', `Quitar ${input.dataset.label}`);
            button.addEventListener('click', () => { input.checked = false; updateServices(); });
            selected.append(button);
        }
    }
    choices.forEach(input => input.addEventListener('change', updateServices));
    picker.querySelector('[data-service-filter]').addEventListener('input', event => {
        const term = event.target.value.trim().toLocaleLowerCase('es');
        let visible = 0;
        picker.querySelectorAll('[data-service-option]').forEach(option => {
            option.hidden = !option.textContent.toLocaleLowerCase('es').includes(term);
            if (!option.hidden) visible++;
        });
        picker.querySelector('[data-service-empty]').hidden = visible > 0;
    });
    document.addEventListener('click', event => { if (!picker.contains(event.target)) details.open = false; });
    picker.addEventListener('keydown', event => { if (event.key === 'Escape') { details.open = false; details.querySelector('summary').focus(); } });
    const files = form.querySelector('[data-order-files]');
    const fileFeedback = form.querySelector('[data-file-feedback]');
    files.addEventListener('change', () => {
        const list = Array.from(files.files);
        const bytes = list.reduce((sum, file) => sum + file.size, 0);
        let error = '';
        if (list.length > 5 || bytes > 20 * 1024 * 1024) error = 'Seleccione hasta 5 archivos y 20 MB en total.';
        else if (list.some(file => file.size > 10 * 1024 * 1024)) error = 'Ningún archivo puede superar 10 MB.';
        else if (list.some(file => !/\.(pdf|docx|jpe?g|png|webp)$/i.test(file.name))) error = 'Use PDF, DOCX, JPG, PNG o WebP.';
        files.setCustomValidity(error);
        fileFeedback.textContent = error || (list.length ? `${list.length} archivos · ${(bytes / 1024 / 1024).toFixed(2)} MB: ${list.map(file => file.name).join(', ')}` : 'Sin documentos seleccionados.');
        if (error) files.reportValidity();
    });
    form.addEventListener('submit', event => {
        if (!choices.some(input => input.checked)) {
            event.preventDefault();
            details.open = true;
            details.querySelector('summary').focus();
            picker.querySelector('[data-service-label]').textContent = 'Seleccione al menos un servicio';
            return;
        }
        const button = form.querySelector('[data-order-submit]');
        button.disabled = true;
        button.textContent = 'Enviando solicitud…';
    });
    updateServices();
})();
