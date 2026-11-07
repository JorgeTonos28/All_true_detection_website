(() => {
    'use strict';
    const form = document.querySelector('[data-order-compose]');
    if (!form) return;
    const servicesDialog = form.querySelector('[data-services-dialog]');
    const choices = Array.from(servicesDialog.querySelectorAll('input[type=checkbox]'));
    const list = form.querySelector('[data-people-list]');
    const feedback = form.querySelector('[data-file-feedback]');
    const errorBox = form.querySelector('[data-compose-error]');
    const identityServices = ['university-degree', 'exequatur', 'identity-verification'];
    let validationRun = 0;
    form.noValidate = true;
    const checkedCodes = () => choices.filter(input => input.checked).map(input => input.value);
    const entries = () => Array.from(list.querySelectorAll('[data-person-entry]'));
    const personCodes = entry => Array.from(entry.querySelectorAll('[data-person-service]')).filter(input => input.checked && !input.disabled).map(input => input.value);
    function error(message) { errorBox.textContent = message; errorBox.hidden = !message; }
    function open(dialog) { if (!dialog.open) dialog.showModal(); }
    function invalidField(root) {
        return Array.from(root.querySelectorAll('input,textarea,select')).find(input => input.willValidate && !input.validity.valid);
    }
    function showInvalid(input) {
        const dialog = input.closest('dialog');
        if (dialog) open(dialog);
        const details = input.closest('details');
        if (details) details.open = true;
        input.focus();
        input.reportValidity();
    }
    function summarize(entry, index) {
        const codes = personCodes(entry);
        const first = entry.querySelector('[data-person-first]').value.trim();
        const last = entry.querySelector('[data-person-last]').value.trim();
        entry.querySelector('[data-person-name]').textContent = [first, last].filter(Boolean).join(' ') || `Persona ${index + 1}`;
        entry.querySelector('[data-person-avatar]').textContent = first ? first.slice(0, 1).toLocaleUpperCase('es') : String(index + 1);
        entry.querySelector('[data-person-services]').textContent = choices.filter(input => codes.includes(input.value)).map(input => input.dataset.label).join(' · ') || 'Sin servicios asignados';
        const complete = codes.length > 0 && !invalidField(entry);
        const status = entry.querySelector('[data-person-completeness]');
        status.textContent = complete ? 'Ficha completa' : 'Pendiente de completar';
        status.classList.toggle('person-complete', complete);
    }
    function syncPerson(entry, index) {
        const available = checkedCodes().filter(code => code !== 'training-equipment');
        entry.hidden = available.length === 0;
        entry.querySelector('[data-person-fields]').disabled = available.length === 0;
        entry.querySelectorAll('[name]').forEach(input => { input.name = input.name.replace(/^(people|person_evidence)\[[^\]]+\]/, `$1[${index}]`); });
        const heading = entry.querySelector('[data-person-heading]');
        heading.id = `person-editor-heading-${index}`;
        entry.querySelector('[data-person-dialog]').setAttribute('aria-labelledby', heading.id);
        entry.querySelectorAll('[data-person-service]').forEach(input => {
            input.disabled = !available.includes(input.value);
            input.closest('[data-person-service-option]').hidden = input.disabled;
        });
        const codes = personCodes(entry);
        entry.querySelectorAll('[data-person-conditional]').forEach(section => {
            const enabled = section.dataset.personConditional.split(',').some(code => codes.includes(code));
            section.hidden = !enabled;
            section.disabled = !enabled;
        });
        const needsIdentity = identityServices.some(code => codes.includes(code));
        entry.querySelector('[data-person-address]').required = needsIdentity || codes.includes('home-visits');
        entry.querySelector('[data-address-required]').textContent = entry.querySelector('[data-person-address]').required ? '*' : '(opcional)';
        entry.querySelector('input[name$="[identity_front]"]').required = codes.some(code => ['university-degree', 'exequatur'].includes(code));
        summarize(entry, index);
    }
    function updatePeople() {
        entries().forEach(syncPerson);
        const needed = checkedCodes().some(code => code !== 'training-equipment');
        form.querySelector('[data-people-section]').hidden = !needed;
        form.querySelector('[data-people-empty]').hidden = entries().length > 0;
        const count = needed ? entries().length : 0;
        form.querySelector('[data-person-count]').textContent = count;
        form.querySelector('[data-person-count-label]').textContent = count === 1 ? 'persona' : 'personas';
        form.querySelector('[data-add-person]').disabled = entries().length >= 10;
    }
    function updateServices() {
        const checked = choices.filter(input => input.checked);
        form.querySelector('[data-service-label]').textContent = checked.length ? `${checked.length} ${checked.length === 1 ? 'servicio seleccionado' : 'servicios seleccionados'}` : 'Seleccionar servicios';
        form.querySelector('[data-service-count]').textContent = checked.length;
        form.querySelector('[data-service-count-label]').textContent = checked.length === 1 ? 'servicio distinto' : 'servicios distintos';
        form.querySelector('[data-services-count]').textContent = `${checked.length} ${checked.length === 1 ? 'seleccionado' : 'seleccionados'}`;
        const selected = form.querySelector('[data-selected-services]');
        selected.replaceChildren();
        checked.forEach(input => {
            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'service-chip';
            chip.textContent = `${input.dataset.label} ×`;
            chip.setAttribute('aria-label', `Quitar ${input.dataset.label}`);
            chip.addEventListener('click', () => { input.checked = false; updateServices(); });
            selected.append(chip);
        });
        form.querySelectorAll('[data-service-guidance]').forEach(notice => {
            notice.hidden = !checked.some(input => input.value === notice.dataset.serviceGuidance);
        });
        updatePeople();
        void validateFiles();
    }
    async function validateFiles() {
        const run = ++validationRun;
        const fields = Array.from(form.querySelectorAll('input[type=file]'));
        fields.forEach(input => input.setCustomValidity(''));
        const active = fields.filter(input => !input.matches(':disabled'));
        const files = active.flatMap(input => Array.from(input.files).map(file => ({ input, file })));
        const total = files.reduce((sum, item) => sum + item.file.size, 0);
        let message = '';
        let target = active[0];
        if (files.length > 20 || total > 20 * 1024 * 1024) message = 'Este envío admite hasta 20 archivos y 20 MB entre todas las personas. Reduzca el lote si es necesario.';
        const hashes = new Map();
        if (!message) for (const { input, file } of files) {
            target = input;
            const evidence = input.name.startsWith('person_evidence');
            const selfie = /\[selfie(?:_extra)?\]$/.test(input.name);
            if (file.size > 10 * 1024 * 1024) message = 'Ningún archivo puede superar 10 MB.';
            else if (!/\.(pdf|docx|jpe?g|png|webp)$/i.test(file.name)) message = 'Use PDF, DOCX sin macros, JPG, PNG o WebP; no se admite audio, vídeo ni HEIC.';
            else if (selfie && !/\.(jpe?g|png|webp)$/i.test(file.name)) message = 'Las selfies deben ser imágenes JPG, PNG o WebP.';
            else if (evidence && !selfie && !/\.(pdf|jpe?g|png|webp)$/i.test(file.name)) message = 'La cédula, el título y el exequátur deben ser PDF o imágenes JPG, PNG o WebP.';
            if (!message && window.crypto?.subtle) {
                const buffer = await file.arrayBuffer();
                const hash = Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256', buffer))).map(byte => byte.toString(16).padStart(2, '0')).join('');
                if (hashes.has(hash)) message = `El archivo «${file.name}» repite el contenido de «${hashes.get(hash)}». Seleccione documentos diferentes.`;
                hashes.set(hash, file.name);
            }
            if (message) break;
        }
        if (run !== validationRun) return false;
        target?.setCustomValidity(message);
        feedback.textContent = message || (files.length ? `${files.length} archivos · ${(total / 1024 / 1024).toFixed(2)} MB seleccionados. Aún no se han enviado.` : 'Sin archivos seleccionados. Las miniaturas se generan en su navegador.');
        feedback.classList.toggle('compose-error', Boolean(message));
        entries().forEach(summarize);
        return !message;
    }
    form.querySelector('[data-open-services]').addEventListener('click', () => open(servicesDialog));
    choices.forEach(input => input.addEventListener('change', updateServices));
    const catalogSearch = servicesDialog.querySelector('[data-service-filter]');
    const catalogGroups = Array.from(servicesDialog.querySelectorAll('[data-service-group]'));
    const catalogTabs = Array.from(servicesDialog.querySelectorAll('[data-catalog-category]'));
    const searchText = value => value.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLocaleLowerCase('es');
    let catalogCategory = 'all';
    function filterCatalog() {
        const words = searchText(catalogSearch.value).trim().split(/\s+/);
        let visible = 0;
        catalogGroups.forEach(group => {
            let matches = 0;
            const inCategory = catalogCategory === 'all' || group.dataset.serviceGroup === catalogCategory;
            const heading = group.querySelector('h3').textContent;
            group.querySelectorAll('[data-service-option]').forEach(option => {
                const text = searchText(`${heading} ${option.textContent}`);
                option.hidden = !inCategory || !words.every(word => text.includes(word));
                if (!option.hidden) matches++;
            });
            group.hidden = matches === 0;
            visible += matches;
        });
        servicesDialog.querySelector('[data-service-empty]').hidden = visible > 0;
        servicesDialog.querySelector('[data-catalog-list]').scrollTop = 0;
    }
    catalogSearch.addEventListener('input', filterCatalog);
    catalogTabs.forEach(tab => tab.addEventListener('click', () => {
        catalogCategory = tab.dataset.catalogCategory;
        catalogTabs.forEach(item => item.setAttribute('aria-pressed', String(item === tab)));
        filterCatalog();
    }));
    form.querySelector('[data-add-person]').addEventListener('click', () => {
        if (entries().length >= 10) return;
        const clone = form.querySelector('[data-person-template]').content.cloneNode(true);
        const entry = clone.querySelector('[data-person-entry]');
        entry.querySelectorAll('[data-person-service]').forEach(input => { input.checked = checkedCodes().includes(input.value); });
        list.append(clone);
        updatePeople();
        open(entry.querySelector('[data-person-dialog]'));
    });
    form.addEventListener('click', async event => {
        const entry = event.target.closest('[data-person-entry]');
        if (event.target.closest('[data-close-dialog]')) event.target.closest('dialog').close();
        if (!entry) return;
        if (event.target.closest('[data-edit-person]')) open(entry.querySelector('[data-person-dialog]'));
        if (event.target.closest('[data-remove-person]')) {
            if (!window.confirm('¿Quitar esta persona y los archivos seleccionados en su ficha?')) return;
            window.ATDPreviews?.releaseTree(entry);
            entry.remove();
            updatePeople();
            void validateFiles();
        }
        if (event.target.closest('[data-save-person]')) {
            const service = entry.querySelector('[data-person-service]:not(:disabled)');
            service?.setCustomValidity(personCodes(entry).length ? '' : 'Seleccione al menos un servicio para esta persona.');
            await validateFiles();
            const invalid = invalidField(entry);
            if (invalid) { showInvalid(invalid); return; }
            entry.querySelector('[data-person-dialog]').close();
            updatePeople();
            entry.querySelector('[data-edit-person]').focus();
        }
    });
    form.addEventListener('change', event => {
        const entry = event.target.closest('[data-person-entry]');
        if (entry) {
            entry.querySelectorAll('[data-person-service]').forEach(input => input.setCustomValidity(''));
            updatePeople();
        }
        if (event.target.matches('input[type=file],[data-person-service]')) void validateFiles();
    });
    form.addEventListener('input', event => {
        const entry = event.target.closest('[data-person-entry]');
        if (entry) summarize(entry, entries().indexOf(entry));
    });
    form.addEventListener('keydown', event => {
        if (event.key !== 'Enter' || !event.target.matches('input:not([type]),input[type=text],input[type=search],input[type=email],input[type=number]')) return;
        const dialog = event.target.closest('dialog');
        if (!dialog) return;
        event.preventDefault();
        dialog.querySelector('[data-save-person]')?.click();
    });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        error('');
        const codes = checkedCodes();
        if (!codes.length) { error('Seleccione al menos un servicio.'); open(servicesDialog); return; }
        if (codes.some(code => code !== 'training-equipment') && !entries().length) { error('Agregue al menos una persona para los servicios solicitados.'); form.querySelector('[data-add-person]').focus(); return; }
        const unassigned = codes.filter(code => code !== 'training-equipment' && !entries().some(entry => personCodes(entry).includes(code)));
        if (unassigned.length) { error('Asigne cada servicio seleccionado a por lo menos una persona.'); return; }
        for (const entry of entries().filter(entry => !entry.hidden)) {
            if (!personCodes(entry).length) { error('Cada persona debe tener por lo menos un servicio.'); open(entry.querySelector('[data-person-dialog]')); return; }
        }
        if (!await validateFiles()) { error('Revise los archivos seleccionados antes de enviar.'); }
        const invalid = invalidField(form);
        if (invalid) { showInvalid(invalid); return; }
        if (!errorBox.hidden) return;
        const button = form.querySelector('[data-order-submit]');
        button.disabled = true;
        button.textContent = 'Enviando solicitud…';
        HTMLFormElement.prototype.submit.call(form);
    });
    updateServices();
    form.querySelector('[data-order-submit]').disabled = false;
})();
