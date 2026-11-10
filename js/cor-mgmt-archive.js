/* Explicit bounded maintenance; a page load never starts a command. */
(() => {
    'use strict';
    document.querySelectorAll('[data-archive-form]').forEach(form => {
        const stop = form.querySelector('[data-archive-stop]');
        const status = form.querySelector('[data-archive-status]');
        let running = false;
        let stopped = false;
        stop.addEventListener('click', () => { stopped = true; });
        form.querySelectorAll('[data-archive-run]').forEach(button => {
            button.addEventListener('click', async () => {
                if (running || !form.reportValidity()) return;
                const endpoint = new URL(form.dataset.endpoint, window.location.href);
                if (endpoint.origin !== window.location.origin) return;
                const fields = new FormData(form);
                fields.set('action', 'cor_mgmt_archive_step');
                fields.set('operation', button.dataset.archiveRun);
                if (button.dataset.archiveRun === 'live-step') fields.set('snapshot', '');
                running = true;
                stopped = false;
                const controls = Array.from(form.querySelectorAll('button'));
                controls.forEach(control => { control.disabled = true; });
                stop.disabled = false;
                try {
                    while (!stopped) {
                        const response = await fetch(endpoint, {method: 'POST', body: fields, credentials: 'same-origin', redirect: 'error'});
                        if (!response.ok) throw new Error('Processing stopped. Reload and review the status.');
                        const result = await response.json();
                        if (result.success !== true || !result.data || typeof result.data.state !== 'string'
                            || !Number.isSafeInteger(result.data.copied) || result.data.copied < 0
                            || result.data.snapshot !== (fields.get('snapshot') || '')) throw new Error('Unexpected response. Reload the status.');
                        status.textContent = `${result.data.state}: ${result.data.copied}`;
                        if (['ready', 'sealed'].includes(result.data.state)) break;
                        if (!['prepare', 'copy', 'verify'].includes(result.data.state)) throw new Error('Processing stopped. Review the state.');
                    }
                } catch (error) {
                    status.textContent = error.message;
                } finally {
                    running = false;
                    controls.forEach(control => { control.disabled = false; });
                    stop.disabled = true;
                }
            });
        });
    });
})();
