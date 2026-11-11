/* Database administration uses bounded, nonce-protected same-origin requests. */
(() => {
    const panel = document.querySelector('#cor-mgmt-schema');
    if (!panel) return;
    const output = panel.querySelector('[data-report]');
    const progress = panel.querySelector('[data-progress]');
    const progressText = panel.querySelector('[data-progress-text]');
    const spinner = panel.querySelector('[data-spinner]');
    const report = (message, processing) => {
        if (progress && progressText) {
            progress.hidden = false;
            progressText.textContent = message;
        }
        if (spinner) spinner.hidden = !processing;
    };
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
        let reloading = false;
        const controls = Array.from(panel.querySelectorAll('button'), item => ({item, disabled: item.disabled}));
        const label = button.textContent;
        const waiting = panel.dataset.busyText || 'Please wait, processing...';
        controls.forEach(({item}) => { item.disabled = true; });
        button.textContent = waiting;
        output.textContent = '';
        output.setAttribute('aria-busy', 'true');
        report(waiting, true);
        try {
            let result = await request(button.dataset.operation, button.dataset.run || '');
            if (result.fingerprint) fingerprint = result.fingerprint;
            if (result.reload) {
                reloading = true;
                report(panel.dataset.reloadText || 'Processing complete. Reloading the page...', true);
                window.location.reload();
                return;
            }
            while (result.run_id && result.state === 'running') {
                result = await request('step', result.run_id);
            }
            report(panel.dataset.completeText || 'Processing complete.', false);
        } catch (error) {
            reloading = false;
            output.textContent = error.message;
            report(panel.dataset.errorText || 'Processing failed. Please review the message below.', false);
        } finally {
            if (!reloading) {
                busy = false;
                controls.forEach(({item, disabled}) => { item.disabled = disabled; });
                button.textContent = label;
                output.setAttribute('aria-busy', 'false');
            }
        }
    });
})();
