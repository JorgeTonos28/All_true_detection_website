const contactDialog = document.querySelector('#evaluation-contact');
let contactTrigger;
if (contactDialog && typeof contactDialog.showModal === 'function') {
    document.querySelectorAll('[data-contact-open]').forEach(trigger => {
        trigger.addEventListener('click', event => {
            event.preventDefault();
            contactTrigger = trigger;
            contactDialog.showModal();
            document.body.classList.add('contact-open');
        });
    });
    contactDialog.querySelector('[data-contact-close]').addEventListener('click', () => contactDialog.close());
    contactDialog.addEventListener('click', event => {
        const box = contactDialog.getBoundingClientRect();
        if (event.target === contactDialog && (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom)) contactDialog.close();
    });
    contactDialog.addEventListener('close', () => {
        document.body.classList.remove('contact-open');
        contactTrigger?.focus();
    });
}
document.querySelectorAll('[data-public-contact]').forEach(form => {
    form.addEventListener('submit', async event => {
        if (!window.fetch) return;
        event.preventDefault();
        const button = form.querySelector('[type=submit]');
        if (button.disabled) return;
        const feedback = form.querySelector('[data-contact-feedback]');
        const label = button.textContent;
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 30000);
        button.disabled = true;
        button.textContent = 'Enviando…';
        feedback.hidden = true;
        feedback.removeAttribute('data-error');
        try {
            const response = await fetch(form.action, {method:'POST',body:new FormData(form),headers:{Accept:'application/json'},credentials:'same-origin',signal:controller.signal});
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                const errors = Object.values(data.errors || {}).flat();
                throw new Error(response.status === 429 ? 'Ha alcanzado el límite de consultas. Inténtelo más tarde.' : errors.join(' ') || 'No se pudo enviar. Revise los datos o recargue la página si su sesión venció.');
            }
            feedback.textContent = data.message || 'Consulta recibida. Nos pondremos en contacto con usted.';
            form.reset();
        } catch (error) {
            feedback.dataset.error = 'true';
            feedback.textContent = error.name === 'AbortError' ? 'No pudimos confirmar la recepción. Revise si recibió el aviso antes de volver a enviar.' : error.message;
        } finally {
            clearTimeout(timer);
            feedback.hidden = false;
            button.disabled = false;
            button.textContent = label;
        }
    });
});
