(() => {
    'use strict';
    document.addEventListener('invalid', event => {
        let parent = event.target.parentElement;
        while (parent) {
            if (parent.tagName === 'DETAILS') parent.open = true;
            parent = parent.parentElement;
        }
    }, true);
    const form = document.querySelector('[data-attachment-form]');
    if (!form) return;
    const person = form.querySelector('[data-upload-person]');
    const purpose = form.querySelector('[data-upload-purpose]');
    const file = form.querySelector('input[type=file]');
    function update() {
        const codes = person.selectedOptions[0]?.dataset.serviceCodes?.split(',') || [];
        const hasIdentity = codes.some(code => ['university-degree', 'exequatur', 'identity-verification'].includes(code));
        const allowed = ['supporting'];
        if (hasIdentity) allowed.push('identity_front', 'identity_back');
        if (codes.includes('university-degree')) allowed.push('degree_document');
        if (codes.includes('exequatur')) allowed.push('exequatur_document');
        if (codes.includes('identity-verification')) allowed.push('selfie', 'selfie_extra');
        Array.from(purpose.options).forEach(option => { option.disabled = !allowed.includes(option.value); });
        if (!allowed.includes(purpose.value)) purpose.value = 'supporting';
        file.accept = purpose.value.startsWith('selfie') ? '.jpg,.jpeg,.png,.webp' : purpose.value === 'supporting' ? '.pdf,.docx,.jpg,.jpeg,.png,.webp' : '.pdf,.jpg,.jpeg,.png,.webp';
        file.setCustomValidity('');
    }
    person.addEventListener('change', update);
    purpose.addEventListener('change', update);
    file.addEventListener('change', () => file.setCustomValidity(''));
    form.addEventListener('submit', event => {
        const selected = file.files[0];
        if (selected?.size > 10 * 1024 * 1024) {
            event.preventDefault();
            file.setCustomValidity('El archivo no puede superar 10 MB.');
            file.reportValidity();
        }
    });
    update();
})();
