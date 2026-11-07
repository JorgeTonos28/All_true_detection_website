(() => {
    'use strict';
    const menu = document.querySelector('[data-notification-menu]');
    if (!menu) return;
    let pending = false;
    async function refresh() {
        if (pending || document.hidden || menu.querySelector('details')?.open) return;
        pending = true;
        try {
            const response = await fetch(menu.dataset.url, {headers:{Accept:'application/json'},credentials:'same-origin',cache:'no-store'});
            if (response.ok && !response.redirected) {
                const data = await response.json();
                menu.innerHTML = data.html;
            }
        } catch (_) { /* The full inbox remains available after a connection interruption. */ }
        finally { pending = false; }
    }
    setInterval(refresh, 60000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    document.addEventListener('click', event => {
        if (!menu.contains(event.target)) menu.querySelector('details')?.removeAttribute('open');
    });
})();
