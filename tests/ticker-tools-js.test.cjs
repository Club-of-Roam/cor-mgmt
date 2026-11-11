const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

test('ticker tools show progress, preserve POST action and reject duplicate submissions', () => {
    let submit;
    const progress = {hidden: true, querySelector: () => null};
    const text = {};
    const buttons = ['setup', 'start', 'step'].map(value => ({name: 'ticker_action', value, disabled: false}));
    const hidden = [];
    const form = {
        dataset: {busyText: 'Waiting...'},
        addEventListener: (name, callback) => { assert.equal(name, 'submit'); submit = callback; },
        appendChild: input => hidden.push(input),
        querySelector: selector => selector === '[data-progress]' ? progress : selector === '[data-progress-text]' ? text : null,
        querySelectorAll: () => buttons,
        setAttribute: (key, value) => { form[key] = value; },
    };
    vm.runInNewContext(fs.readFileSync('js/cor-mgmt-ticker-tools.js', 'utf8'), {
        document: {querySelector: () => form, createElement: () => ({})},
    });
    let prevented = 0;
    const event = {submitter: buttons[0], preventDefault: () => { prevented++; }};
    submit(event);
    assert.equal(prevented, 0);
    assert.equal(progress.hidden, false);
    assert.equal(text.textContent, 'Waiting...');
    assert.equal(form['aria-busy'], 'true');
    assert.ok(buttons.every(button => button.disabled));
    assert.equal(hidden.length, 1);
    assert.equal(hidden[0].name, 'ticker_action');
    assert.equal(hidden[0].value, 'setup');
    submit(event);
    assert.equal(prevented, 1);
    assert.equal(hidden.length, 1);
});

const state = (phase, cursor, verified = 0) => ({state: phase, cursor, verified, count: 2, max: 500, shape: 'source-shape', issues: 3});
function harness(responses, action = 'https://example.test/wp-admin/tools.php?page=cor-mgmt-ticker') {
    const handlers = {};
    const automatic = {disabled: false, hidden: true, addEventListener: (_, fn) => { handlers.auto = fn; }};
    const stop = {hidden: true, addEventListener: (_, fn) => { handlers.stop = fn; }};
    const spinner = {hidden: true};
    const progress = {hidden: true, querySelector: () => spinner};
    const text = {};
    const meter = {removeAttribute: key => { delete meter[key]; }};
    const output = {textContent: JSON.stringify(state('copy', 100))};
    const nodes = {'[data-transfer-auto]': automatic, '[data-transfer-stop]': stop, '[data-progress]': progress,
        '[data-progress-text]': text, '[data-transfer-progress]': meter, '[data-transfer-meter]': {hidden: true}};
    const controls = [automatic, stop, {disabled: true}, {disabled: false}];
    const form = {
        action, dataset: {copyText: 'Copy', verifyText: 'Verify', issuesText: 'Notices', completeText: 'Complete',
            stoppedText: 'Paused', stoppingText: 'Stopping', errorText: 'Reload before resuming'},
        querySelector: key => nodes[key] || null,
        querySelectorAll: () => controls,
        addEventListener: (_, fn) => { handlers.submit = fn; },
        setAttribute: (key, value) => { form[key] = value; },
    };
    const calls = [];
    class Form {
        constructor(value) { assert.equal(value, form); this.values = {cor_mgmt_nonce: 'test-nonce'}; }
        set(key, value) { this.values[key] = value; }
    }
    class Parser {
        parseFromString(value) {
            const parsed = JSON.parse(value);
            return {querySelector: selector => {
                if (selector === '[data-ticker-state]') return {textContent: JSON.stringify(parsed.state)};
                if (selector === '[data-ticker-error]') return parsed.error ? {} : null;
                return parsed.valid === false ? null : {};
            }};
        }
    }
    vm.runInNewContext(fs.readFileSync('js/cor-mgmt-ticker-tools.js', 'utf8'), {
        document: {querySelector: selector => selector === '[data-ticker-state]' ? output : form},
        window: {location: {href: action, origin: 'https://example.test'}}, URL, FormData: Form, DOMParser: Parser,
        fetch: async (url, options) => {
            calls.push({url, options});
            const value = responses.shift();
            if (typeof value === 'function') return value();
            if (!value) throw new Error('unexpected_request');
            return {ok: true, text: async () => JSON.stringify(value)};
        },
    });
    return {handlers, calls, automatic, stop, spinner, progress, text, meter, output, form, controls};
}

test('automatic ticker processing starts only on click and runs copy then verification sequentially', async () => {
    const h = harness([{state: state('verify', 0)}, {state: state('verify', 100, 1)}, {state: state('native', 500, 2)}]);
    assert.equal(h.calls.length, 0);
    assert.equal(h.automatic.hidden, false);
    await h.handlers.auto();
    assert.equal(h.calls.length, 3);
    for (const call of h.calls) {
        assert.equal(call.options.method, 'POST');
        assert.equal(call.options.credentials, 'same-origin');
        assert.equal(call.options.redirect, 'error');
        assert.equal(call.options.body.values.cor_mgmt_nonce, 'test-nonce');
        assert.equal(call.options.body.values.ticker_action, 'step');
    }
    assert.equal(h.text.textContent, 'Complete');
    assert.equal(h.spinner.hidden, true);
    assert.equal(h.automatic.disabled, true);
    assert.equal(h.form['aria-busy'], 'false');
    assert.equal(h.meter.value, 2);
    assert.equal(h.controls[2].disabled, true);
});

test('stop waits for the current request and preserves resumable progress without another step', async () => {
    let finish;
    const h = harness([() => new Promise(resolve => { finish = resolve; })]);
    const running = h.handlers.auto();
    assert.equal(h.calls.length, 1);
    assert.equal(h.progress.hidden, false);
    assert.match(h.text.textContent, /Copy: 100/);
    await h.handlers.auto();
    h.handlers.stop();
    assert.equal(h.text.textContent, 'Stopping');
    finish({ok: true, text: async () => JSON.stringify({state: state('copy', 200)})});
    await running;
    assert.equal(h.calls.length, 1);
    assert.equal(h.text.textContent, 'Paused');
    assert.equal(h.automatic.disabled, false);
    assert.equal(JSON.parse(h.output.textContent).cursor, 200);
});

test('foreign action receives no nonce or processing request', async () => {
    const h = harness([], 'https://foreign.test/tools.php');
    await h.handlers.auto();
    assert.equal(h.calls.length, 0);
    assert.match(h.text.textContent, /same_origin_required/);
    assert.equal(h.automatic.disabled, true);
});

test('errors, stale progress, changed source and malformed responses stop without automatic retry', async () => {
    for (const response of [
        {error: true, state: state('copy', 200)},
        {valid: false, state: state('copy', 200)},
        {state: state('copy', 100)},
        {state: {...state('copy', 200), shape: 'changed'}},
        {state: {...state('copy', 200), count: -1}},
        {state: state('native', 500, 2)},
        () => { throw new Error('network_failure'); },
        () => ({ok: false}),
        () => ({ok: true, text: async () => '<html>login</html>'}),
    ]) {
        const h = harness([response]);
        await h.handlers.auto();
        assert.equal(h.calls.length, 1);
        assert.equal(h.automatic.disabled, true);
        assert.equal(h.spinner.hidden, true);
        assert.match(h.text.textContent, /Reload before resuming/);
        await h.handlers.auto();
        assert.equal(h.calls.length, 1);
    }
});

test('ticker tools script safely ignores other pages', () => {
    vm.runInNewContext(fs.readFileSync('js/cor-mgmt-ticker-tools.js', 'utf8'), {
        document: {querySelector: () => null},
    });
});
