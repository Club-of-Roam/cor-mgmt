const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../js/h3-mgmt-schema.js'), 'utf8');
function harness(responses, foreign = false, hooks = {}) {
    const calls = [];
    const output = {textContent: '', attributes: {}, setAttribute(name, value) { this.attributes[name] = value; }};
    const controls = [{disabled: false, textContent: 'Set up storage'}, {disabled: false, textContent: 'Other action'}];
    const progress = {hidden: true};
    const progressText = {textContent: ''};
    const spinner = {hidden: true};
    let reloads = 0;
    let listener;
    const panel = {
        dataset: {url: foreign ? 'https://other.invalid/ajax' : 'https://example.invalid/wp-admin/admin-ajax.php', nonce: 'synthetic-nonce'},
        querySelector: selector => ({'[data-report]': output, '[data-progress]': progress, '[data-progress-text]': progressText, '[data-spinner]': spinner}[selector] || {checked: true}),
        querySelectorAll: () => controls,
        addEventListener: (_, callback) => { listener = callback; },
    };
    const context = {
        document: {querySelector: () => panel},
        window: {location: {href: 'https://example.invalid/wp-admin/tools.php', origin: 'https://example.invalid', reload() { reloads++; }}},
        URL, FormData,
        fetch: async (url, options) => { calls.push({url, options}); await hooks.beforeResponse?.(); const next = responses.shift(); return {ok: next.success, json: async () => next}; },
    };
    vm.runInNewContext(source, context);
    return {calls, output, controls, progress, progressText, spinner, reloads: () => reloads, click: operation => {
        controls[0].dataset = {operation, run: 'synthetic-run'};
        return listener({target: {closest: () => controls[0]}});
    }};
}
test('schema batches send nonce and acknowledgments and stop after verification', async () => {
    const h = harness([
        {success: true, data: {run_id: 'synthetic-run', state: 'running'}},
        {success: true, data: {run_id: 'synthetic-run', state: 'running'}},
        {success: true, data: {run_id: 'synthetic-run', state: 'verified'}},
    ]);
    await h.click('step');
    assert.equal(h.calls.length, 3);
    for (const call of h.calls) {
        assert.equal(call.options.method, 'POST'); assert.equal(call.options.credentials, 'same-origin');
        assert.equal(call.options.body.get('nonce'), 'synthetic-nonce'); assert.equal(call.options.body.get('backup'), '1');
        assert.equal(call.options.body.get('quiesced'), '1'); assert.equal(call.options.body.get('action'), 'cor_mgmt_schema');
    }
    assert.match(h.output.textContent, /verified/); assert.ok(h.controls.every(item => !item.disabled));
    assert.equal(h.progressText.textContent, 'Processing complete.'); assert.equal(h.spinner.hidden, true);
    assert.equal(h.output.attributes['aria-busy'], 'false');
});
test('schema error stops automatic processing and restores controls', async () => {
    const h = harness([{success: false, data: {code: 'source_changed'}}]);
    await h.click('step'); assert.equal(h.calls.length, 1); assert.equal(h.output.textContent, 'source_changed');
    assert.ok(h.controls.every(item => !item.disabled));
    assert.match(h.progressText.textContent, /Processing failed/); assert.equal(h.spinner.hidden, true);
});
test('schema tool refuses foreign origins before sending credentials', async () => {
    const h = harness([], true);
    await h.click('preflight'); assert.equal(h.calls.length, 0); assert.equal(h.output.textContent, 'same_origin_required');
});

test('slow setup shows immediate progress and prevents another command until navigation', async () => {
    let release;
    const pending = new Promise(resolve => { release = resolve; });
    const h = harness([{success: true, data: {reload: true}}], false, {beforeResponse: () => pending});
    assert.equal(h.progress.hidden, true); assert.equal(h.calls.length, 0);
    const operation = h.click('participation-setup');
    assert.equal(h.progress.hidden, false); assert.equal(h.spinner.hidden, false);
    assert.match(h.progressText.textContent, /Please wait/); assert.match(h.controls[0].textContent, /Please wait/);
    assert.equal(h.output.attributes['aria-busy'], 'true'); assert.ok(h.controls.every(item => item.disabled));
    await h.click('results-setup'); assert.equal(h.calls.length, 1);
    release(); await operation;
    assert.equal(h.reloads(), 1); assert.match(h.progressText.textContent, /Reloading/);
    assert.ok(h.controls.every(item => item.disabled)); assert.equal(h.spinner.hidden, false);
    await h.click('results-setup'); assert.equal(h.calls.length, 1);
});

test('network failure clears the busy indicator and preserves previously disabled controls', async () => {
    const h = harness([], false, {beforeResponse: () => { throw new Error('Connection failed'); }});
    h.controls[1].disabled = true;
    await h.click('participation-setup');
    assert.equal(h.output.textContent, 'Connection failed'); assert.match(h.progressText.textContent, /Processing failed/);
    assert.equal(h.spinner.hidden, true); assert.equal(h.controls[0].disabled, false); assert.equal(h.controls[1].disabled, true);
    assert.equal(h.controls[0].textContent, 'Set up storage'); assert.equal(h.output.attributes['aria-busy'], 'false');
});
