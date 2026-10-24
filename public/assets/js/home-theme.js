(() => {
    let preference = 'system';
    try { preference = localStorage.getItem('atd-site-theme') || 'system'; } catch {}
    const query = matchMedia('(prefers-color-scheme: dark)');
    const apply = () => document.documentElement.dataset.theme = preference === 'system' ? (query.matches ? 'dark' : 'light') : preference;
    if (!['system','light','dark'].includes(preference)) preference = 'system';
    apply();
    query.addEventListener('change', apply);
    document.addEventListener('DOMContentLoaded', () => {
        const select = document.querySelector('[data-theme-preference]');
        if (!select) return;
        select.value = preference;
        select.addEventListener('change', () => {
            preference = select.value;
            try { localStorage.setItem('atd-site-theme', preference); } catch {}
            apply();
        });
    });
})();
