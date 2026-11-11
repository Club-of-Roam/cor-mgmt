/* Keep native POST navigation and its submitter value while displaying progress. */
(() => {
    const form = document.querySelector('form[data-ticker-tools]');
    if (!form) return;
    let busy = false;
    const automatic = form.querySelector('[data-transfer-auto]');
    const stop = form.querySelector('[data-transfer-stop]');
    const output = document.querySelector('[data-ticker-state]');
    const progress = form.querySelector('[data-progress]');
    const text = form.querySelector('[data-progress-text]');
    const spinner = progress.querySelector('.spinner');
    const meter = form.querySelector('[data-transfer-progress]');
    let stopping = false;
    const show = (message, running) => {
        progress.hidden = false;
        text.textContent = message;
        if (spinner) spinner.hidden = !running;
    };
    const readState = node => {
        const state = JSON.parse(node.textContent);
        if (!['pending', 'copy', 'verify', 'native'].includes(state.state)
            || !['cursor', 'count', 'max', 'verified', 'issues'].every(key => Number.isSafeInteger(state[key]) && state[key] >= 0)
            || state.cursor > state.max || state.verified > state.count || typeof state.shape !== 'string') {
            throw new Error('invalid_transfer_state');
        }
        return state;
    };
    const resumable = state => ['copy', 'verify'].includes(state.state);
    const render = state => {
        output.textContent = JSON.stringify(state, null, 2);
        if (state.state === 'copy') {
            meter.removeAttribute('value');
            show(`${form.dataset.copyText}: ${state.cursor} / ${state.max} · ${form.dataset.issuesText}: ${state.issues}`, true);
        } else {
            meter.max = Math.max(1, state.count);
            meter.value = state.verified;
            show(`${form.dataset.verifyText}: ${state.verified} / ${state.count} · ${form.dataset.issuesText}: ${state.issues}`, true);
        }
    };
    if (automatic && stop && output && meter) {
        automatic.hidden = false;
        try {
            automatic.disabled = !resumable(readState(output));
        } catch {
            automatic.disabled = true;
        }
        stop.addEventListener('click', () => {
            stopping = true;
            stop.disabled = true;
            show(form.dataset.stoppingText, true);
        });
        automatic.addEventListener('click', async () => {
            if (busy || automatic.disabled) return;
            busy = true;
            stopping = false;
            const controls = Array.from(form.querySelectorAll('button'), item => ({item, disabled: item.disabled}));
            controls.forEach(({item}) => { item.disabled = true; });
            stop.hidden = false;
            stop.disabled = false;
            form.setAttribute('aria-busy', 'true');
            form.querySelector('[data-transfer-meter]').hidden = false;
            let failed = false;
            let state;
            try {
                state = readState(output);
                if (!resumable(state)) throw new Error('transfer_not_started');
                const url = new URL(form.action || window.location.href, window.location.href);
                if (url.origin !== window.location.origin) throw new Error('same_origin_required');
                let steps = 0;
                while (resumable(state) && !stopping) {
                    if (++steps > 2 * (state.count + 1)) throw new Error('transfer_step_limit');
                    render(state);
                    const body = new FormData(form);
                    body.set('ticker_action', 'step');
                    const response = await fetch(url.href, {method: 'POST', credentials: 'same-origin', redirect: 'error', body});
                    if (!response.ok) throw new Error('ticker_request_failed');
                    const page = new DOMParser().parseFromString(await response.text(), 'text/html');
                    if (page.querySelector('[data-ticker-error]') || !page.querySelector('[data-ticker-success]') || !page.querySelector('form[data-ticker-tools]')) {
                        throw new Error('ticker_operation_rejected');
                    }
                    const next = readState(page.querySelector('[data-ticker-state]'));
                    if (next.count !== state.count || next.max !== state.max || next.shape !== state.shape
                        || (state.state === 'copy' && !['copy', 'verify'].includes(next.state))
                        || (state.state === 'verify' && !['verify', 'native'].includes(next.state))
                        || (next.state === state.state && next.cursor <= state.cursor)
                        || next.verified < state.verified
                        || (next.state === 'native' && next.verified !== next.count)) {
                        throw new Error('unexpected_transfer_progress');
                    }
                    state = next;
                    render(state);
                }
                show(state.state === 'native' ? form.dataset.completeText : form.dataset.stoppedText, false);
            } catch (error) {
                failed = true;
                show(`${form.dataset.errorText} (${error.message})`, false);
            } finally {
                busy = false;
                controls.forEach(({item, disabled}) => { item.disabled = disabled; });
                stop.hidden = true;
                automatic.disabled = failed || !state || !resumable(state);
                form.setAttribute('aria-busy', 'false');
            }
        });
    }
    form.addEventListener('submit', event => {
        if (busy) {
            event.preventDefault();
            return;
        }
        const button = event.submitter;
        if (!button || button.name !== 'ticker_action') return;
        busy = true;
        const action = document.createElement('input');
        action.type = 'hidden';
        action.name = button.name;
        action.value = button.value;
        form.appendChild(action);
        const message = form.dataset.busyText || 'Please wait, processing...';
        form.querySelector('[data-progress-text]').textContent = message;
        form.querySelector('[data-progress]').hidden = false;
        form.setAttribute('aria-busy', 'true');
        button.textContent = message;
        form.querySelectorAll('button').forEach(item => { item.disabled = true; });
    });
})();
