const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../js/h3-mgmt-schema.js'), 'utf8');
function harness(responses, foreign = false) {
    const calls = [];
    const output = {textContent: ''};
    const controls = [{disabled: false}, {disabled: false}];
    let listener;
    const panel = {
        dataset: {url: foreign ? 'https://other.invalid/ajax' : 'https://example.invalid/wp-admin/admin-ajax.php', nonce: 'synthetic-nonce'},
        querySelector: selector => selector === '[data-report]' ? output : {checked: true},
        querySelectorAll: () => controls,
        addEventListener: (_, callback) => { listener = callback; },
    };
    const context = {
        document: {querySelector: () => panel},
        window: {location: {href: 'https://example.invalid/wp-admin/tools.php', origin: 'https://example.invalid', reload() {}}},
        URL, FormData,
        fetch: async (url, options) => { calls.push({url, options}); const next = responses.shift(); return {ok: next.success, json: async () => next}; },
    };
    vm.runInNewContext(source, context);
    return {calls, output, controls, click: operation => listener({target: {closest: () => ({dataset: {operation, run: 'synthetic-run'}})}})};
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
});
test('schema error stops automatic processing and restores controls', async () => {
    const h = harness([{success: false, data: {code: 'source_changed'}}]);
    await h.click('step'); assert.equal(h.calls.length, 1); assert.equal(h.output.textContent, 'source_changed');
    assert.ok(h.controls.every(item => !item.disabled));
});
test('schema tool refuses foreign origins before sending credentials', async () => {
    const h = harness([], true);
    await h.click('preflight'); assert.equal(h.calls.length, 0); assert.equal(h.output.textContent, 'same_origin_required');
});
