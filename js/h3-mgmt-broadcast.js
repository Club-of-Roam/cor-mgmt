/* Explicit, sequential processing. Stopping never aborts an in-flight send. */
(() => {
    const panel = document.querySelector('[data-cor-broadcast-run]');
    if (!panel) return;
    const report = panel.querySelector('[data-batch-report]');
    const stop = panel.querySelector('[data-batch-stop]');
    const buttons = panel.querySelectorAll('[data-batch-operation]');
    const confirmation = panel.querySelector('[data-batch-confirm]');
    let running = false;
    let stopped = false;
    let url;
    try {
        url = new URL(panel.dataset.url, window.location.href);
        if (url.origin !== window.location.origin) throw new Error('same_origin_required');
    } catch {
        report.textContent = panel.dataset.error;
        return;
    }
    panel.hidden = false;
    stop.addEventListener('click', () => { stopped = true; stop.disabled = true; });
    panel.addEventListener('click', async event => {
        const button = event.target.closest('[data-batch-operation]');
        if (!button || running || button.disabled) return;
        const operation = button.dataset.batchOperation;
        if (!['queue', 'send-batch'].includes(operation)) return;
        if (operation === 'send-batch' && !confirmation?.checked) {
            report.setAttribute('role', 'alert');
            report.textContent = panel.dataset.error;
            return;
        }
        running = true;
        stopped = false;
        buttons.forEach(control => { control.disabled = true; });
        stop.disabled = false;
        report.setAttribute('role', 'status');
        panel.setAttribute('aria-busy', 'true');
        try {
            while (!stopped) {
                const body = new FormData();
                body.set('action', 'cor_mgmt_broadcast_step');
                body.set('cor_mgmt_nonce', panel.dataset.nonce);
                body.set('campaign_id', panel.dataset.campaign);
                body.set('revision', panel.dataset.revision);
                body.set('broadcast_operation', operation);
                body.set('confirm', operation === 'send-batch' ? '1' : '0');
                const response = await fetch(url.href, {method: 'POST', credentials: 'same-origin', redirect: 'error', body});
                const result = await response.json();
                if (!response.ok || !result.success || typeof result.data?.revision !== 'string' || typeof result.data?.message !== 'string' || typeof result.data?.done !== 'boolean' || typeof result.data?.stop !== 'boolean') throw new Error('step_rejected');
                panel.dataset.revision = result.data.revision;
                report.textContent = result.data.message;
                if (result.data.stop) throw new Error('transport_stopped');
                if (result.data.done) {
                    report.textContent = panel.dataset.complete + ' ' + report.textContent;
                    break;
                }
            }
            if (stopped) report.textContent = panel.dataset.stopped + ' ' + report.textContent;
        } catch {
            report.setAttribute('role', 'alert');
            report.textContent = panel.dataset.error;
            // No retry and no second start using an unverified revision after an error.
            stopped = true;
        } finally {
            running = false;
            panel.setAttribute('aria-busy', 'false');
            stop.disabled = true;
            if (!stopped) buttons.forEach(control => { control.disabled = false; });
        }
    });
})();
