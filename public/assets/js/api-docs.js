document.querySelectorAll('pre').forEach(pre => {
    const code = pre.querySelector('code');
    if (!code || !navigator.clipboard) return;
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'copy-code';
    button.textContent = 'Copiar';
    button.addEventListener('click', async () => {
        try { await navigator.clipboard.writeText(code.textContent); button.textContent = 'Copiado'; }
        catch { button.textContent = 'Seleccione el texto'; }
    });
    pre.append(button);
});
const search = document.querySelector('[data-endpoint-search]');
search?.addEventListener('input', () => {
    const terms = search.value.toLocaleLowerCase().trim().split(/\s+/).filter(Boolean);
    let count = 0;
    document.querySelectorAll('[data-endpoint]').forEach(row => {
        row.hidden = !terms.every(term => row.textContent.toLocaleLowerCase().includes(term));
        if (!row.hidden) count++;
    });
    document.querySelector('.endpoint-empty').hidden = count > 0;
});
