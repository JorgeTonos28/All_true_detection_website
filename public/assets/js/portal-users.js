(() => {
    'use strict';

    const directory = document.querySelector('[data-user-directory]');
    if (!directory) return;

    const filters = directory.querySelector('[data-user-filters]');
    const results = directory.querySelector('[data-user-results]');
    const feedback = directory.querySelector('[data-search-feedback]');
    const search = filters.elements.q;
    const manageDialog = document.querySelector('[data-manage-dialog]');
    const manageContent = manageDialog.querySelector('[data-manage-content]');
    const inviteDialog = document.querySelector('[data-invite-dialog]');
    let searchTimer;
    let searchRequest;
    let searchVersion = 0;
    let manageRequest;
    let manageVersion = 0;

    async function getJson(url, signal) {
        const response = await fetch(url, {
            headers: {Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
            credentials: 'same-origin',
            cache: 'no-store',
            signal,
        });
        if (response.redirected) {
            window.location.assign(response.url);
            throw new Error('Confirme su acceso para continuar.');
        }
        if (!response.ok) {
            if (response.status === 401 || response.status === 419) {
                window.location.reload();
            }
            if (response.status === 422) {
                const body = await response.json();
                throw new Error(Object.values(body.errors || {}).flat()[0] || 'Revise los filtros.');
            }
            throw new Error(response.status === 429
                ? 'Espere unos segundos antes de continuar.'
                : 'No se pudo cargar la información. Inténtelo de nuevo.');
        }
        return response.json();
    }

    function filterUrl() {
        const url = new URL(filters.action);
        for (const [key, value] of new FormData(filters)) {
            if (value.trim()) url.searchParams.set(key, value.trim());
        }
        return url;
    }

    function cancelSearch() {
        clearTimeout(searchTimer);
        searchRequest?.abort();
        searchVersion += 1;
        return searchVersion;
    }

    async function loadUsers(url, version = cancelSearch(), remember = true) {
        searchRequest = new AbortController();
        results.setAttribute('aria-busy', 'true');
        feedback.textContent = 'Buscando usuarios…';
        try {
            const body = await getJson(url, searchRequest.signal);
            if (version !== searchVersion) return;
            results.innerHTML = body.html;
            if (remember) window.history.replaceState(null, '', url);
            feedback.textContent = `${results.querySelector('.directory-summary strong').textContent}. Resultados actualizados.`;
        } catch (error) {
            if (error.name !== 'AbortError' && version === searchVersion) feedback.textContent = error.message;
        } finally {
            if (version === searchVersion) results.removeAttribute('aria-busy');
        }
    }

    search.addEventListener('input', () => {
        const version = cancelSearch();
        searchTimer = setTimeout(() => loadUsers(filterUrl(), version), 300);
    });
    filters.querySelectorAll('select').forEach(select => select.addEventListener('change', () => loadUsers(filterUrl())));
    filters.addEventListener('submit', event => {
        event.preventDefault();
        loadUsers(filterUrl());
    });

    async function openMember(link) {
        manageRequest?.abort();
        manageRequest = new AbortController();
        const version = ++manageVersion;
        manageContent.textContent = 'Cargando usuario…';
        manageContent.setAttribute('aria-busy', 'true');
        manageDialog.showModal();
        try {
            const body = await getJson(link.href, manageRequest.signal);
            if (version !== manageVersion) return;
            manageContent.innerHTML = body.html;
        } catch (error) {
            if (error.name !== 'AbortError' && version === manageVersion) manageContent.textContent = error.message;
        } finally {
            if (version === manageVersion) manageContent.removeAttribute('aria-busy');
        }
    }

    directory.addEventListener('click', event => {
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
        const page = event.target.closest('.pagination a');
        if (page) {
            event.preventDefault();
            loadUsers(new URL(page.href));
            return;
        }
        const link = event.target.closest('[data-manage-user]')
            || (!event.target.closest('a,button,input,select') && event.target.closest('[data-user-row]')?.querySelector('[data-manage-user]'));
        if (link && !window.getSelection()?.toString()) {
            event.preventDefault();
            openMember(link);
        }
    });

    document.querySelector('[data-open-invite]').addEventListener('click', event => {
        event.preventDefault();
        inviteDialog.showModal();
    });
    document.querySelectorAll('.portal-dialog').forEach(dialog => {
        dialog.addEventListener('click', event => {
            if (event.target.closest('[data-close-dialog]')) dialog.close();
        });
        dialog.addEventListener('close', () => {
            if (dialog === manageDialog) {
                manageVersion += 1;
                manageRequest?.abort();
            }
        });
    });

    const inviteRole = inviteDialog.querySelector('[data-invite-role]');
    const accountFields = inviteDialog.querySelector('[data-client-account]');
    function toggleAccount() {
        accountFields.hidden = inviteRole.value !== 'client';
        accountFields.disabled = accountFields.hidden;
    }
    inviteRole.addEventListener('change', toggleAccount);
    toggleAccount();

    const accountSearch = inviteDialog.querySelector('[data-account-search]');
    const accountSelect = inviteDialog.querySelector('[data-account-select]');
    const accountFeedback = inviteDialog.querySelector('[data-account-feedback]');
    let accountTimer;
    let accountRequest;
    let accountVersion = 0;
    accountSearch.addEventListener('input', () => {
        clearTimeout(accountTimer);
        accountRequest?.abort();
        const version = ++accountVersion;
        const query = accountSearch.value.trim();
        if (query.length < 2) {
            accountFeedback.textContent = 'Escriba al menos 2 caracteres para buscar cuentas.';
            return;
        }
        accountTimer = setTimeout(async () => {
            accountRequest = new AbortController();
            accountFeedback.textContent = 'Buscando cuentas…';
            try {
                const url = new URL(accountSearch.dataset.url);
                url.searchParams.set('q', query);
                const body = await getJson(url, accountRequest.signal);
                if (version !== accountVersion) return;
                const selected = accountSelect.selectedOptions[0];
                const selectedId = accountSelect.value;
                accountSelect.replaceChildren(new Option('Crear una cuenta nueva', ''));
                if (selectedId) accountSelect.add(new Option(selected.textContent, selectedId, true, true));
                for (const account of body.accounts) {
                    if (String(account.id) !== selectedId) accountSelect.add(new Option(`${account.name} · ${account.billing_email}`, account.id));
                }
                accountFeedback.textContent = body.accounts.length
                    ? `${body.accounts.length} ${body.accounts.length === 1 ? 'cuenta encontrada' : 'cuentas encontradas'}. Seleccione una o cree una nueva.`
                    : 'No encontramos cuentas. Puede crear una nueva con la invitación.';
            } catch (error) {
                if (error.name !== 'AbortError' && version === accountVersion) accountFeedback.textContent = error.message;
            }
        }, 300);
    });

    window.addEventListener('popstate', () => {
        const url = new URL(window.location.href);
        for (const key of ['q', 'role', 'status']) filters.elements[key].value = url.searchParams.get(key) || '';
        loadUsers(url, cancelSearch(), false);
    });
    if (inviteDialog.hasAttribute('data-validation-errors')) inviteDialog.showModal();
})();
