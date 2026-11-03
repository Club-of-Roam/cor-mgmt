/* Database administration uses bounded, nonce-protected same-origin requests. */
(() => {
    const panel = document.querySelector('#cor-mgmt-schema');
    if (!panel) return;
    const output = panel.querySelector('[data-report]');
    let fingerprint = '';
    let busy = false;
    const request = async (operation, run = '') => {
        const url = new URL(panel.dataset.url, window.location.href);
        if (url.origin !== window.location.origin) throw new Error('same_origin_required');
        const form = new FormData();
        form.set('action', 'cor_mgmt_schema');
        form.set('nonce', panel.dataset.nonce);
        form.set('operation', operation);
        form.set('run_id', run);
        form.set('fingerprint', fingerprint);
        form.set('backup', panel.querySelector('[data-backup]')?.checked ? '1' : '0');
        form.set('quiesced', panel.querySelector('[data-quiesced]')?.checked ? '1' : '0');
        const response = await fetch(url.href, {method: 'POST', credentials: 'same-origin', body: form});
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.data?.code || 'schema_operation_failed');
        output.textContent = JSON.stringify(result.data, null, 2);
        return result.data;
    };
    panel.addEventListener('click', async event => {
        const button = event.target.closest('button[data-operation]');
        if (!button || busy) return;
        busy = true;
        panel.querySelectorAll('button').forEach(item => { item.disabled = true; });
        try {
            let result = await request(button.dataset.operation, button.dataset.run || '');
            if (result.fingerprint) fingerprint = result.fingerprint;
            if (result.reload) { window.location.reload(); return; }
            while (result.run_id && result.state === 'running') {
                result = await request('step', result.run_id);
            }
        } catch (error) {
            output.textContent = error.message;
        } finally {
            busy = false;
            panel.querySelectorAll('button').forEach(item => { item.disabled = false; });
        }
    });
})();
