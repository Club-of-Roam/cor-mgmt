const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../js/h3-mgmt-broadcast.js'), 'utf8');
function harness(responses, options = {}) {
    const calls = [];
    const report = { textContent: '', setAttribute() {} };
    const controls = ['queue','send-batch'].map(operation => ({disabled: false, dataset: {batchOperation: operation}}));
    const confirmation = {checked: options.confirmed !== false};
    let click, stopClick;
    const stop = {disabled: true, addEventListener: (_, callback) => { stopClick = callback; }};
    const panel = {
        hidden: true,
        dataset: {url: options.foreign ? 'https://other.invalid/ajax' : 'https://example.invalid/ajax', nonce: 'synthetic-nonce', campaign: '17', revision: 'r1', error: 'Request stopped', complete: 'Finished', stopped: 'Stopped after current step'},
        querySelector: selector => selector === '[data-batch-report]' ? report : selector === '[data-batch-stop]' ? stop : confirmation,
        querySelectorAll: () => controls,
        setAttribute() {},
        addEventListener: (_, callback) => { click = callback; },
    };
    vm.runInNewContext(source, {
        document: {querySelector: () => panel},
        window: {location: {href: 'https://example.invalid/admin', origin: 'https://example.invalid'}},
        URL, FormData,
        fetch: async (url, settings) => {
            calls.push({url, settings});
            if (options.onFetch) options.onFetch();
            const next = responses.shift();
            return {ok: next.success, json: async () => next};
        },
    });
    return {calls, controls, panel, report, stop: () => stopClick(), click: operation => click?.({target: {closest: () => controls.find(item => item.dataset.batchOperation === operation)}})};
}
const result = (revision, done, stop = false) => ({success: true, data: {revision, done, stop, message: 'Pending: 1'}});
test('campaign steps carry nonce, identity, fresh revision and same-origin POST without redirects', async () => {
    const h = harness([result('r2', false), result('r3', true)]);
    await h.click('queue');
    assert.equal(h.calls.length, 2);
    assert.equal(h.calls[0].settings.body.get('revision'), 'r1');
    assert.equal(h.calls[1].settings.body.get('revision'), 'r2');
    for (const {settings} of h.calls) {
        assert.equal(settings.method, 'POST'); assert.equal(settings.credentials, 'same-origin'); assert.equal(settings.redirect, 'error');
        assert.equal(settings.body.get('action'), 'cor_mgmt_broadcast_step'); assert.equal(settings.body.get('campaign_id'), '17');
        assert.equal(settings.body.get('cor_mgmt_nonce'), 'synthetic-nonce'); assert.equal(settings.body.get('confirm'), '0');
    }
    assert.match(h.report.textContent, /Finished/); assert.ok(h.controls.every(item => !item.disabled));
});
test('campaign delivery needs explicit confirmation and never starts on page load', async () => {
    const h = harness([], {confirmed: false});
    assert.equal(h.calls.length, 0); await h.click('send-batch'); assert.equal(h.calls.length, 0);
});
test('confirmed campaign delivery sends approval with each bounded step', async () => {
    const h = harness([result('r2', true)]); await h.click('send-batch');
    assert.equal(h.calls[0].settings.body.get('confirm'), '1');
    assert.equal(h.calls[0].settings.body.get('broadcast_operation'), 'send-batch');
});
test('a transport failure stops without retry or a second start using old state', async () => {
    const h = harness([result('r2', false, true)]); await h.click('send-batch'); await h.click('send-batch');
    assert.equal(h.calls.length, 1); assert.equal(h.report.textContent, 'Request stopped'); assert.ok(h.controls.every(item => item.disabled));
});
test('stop waits for the in-flight step and does not abort or send another request', async () => {
    let h; h = harness([result('r2', false)], {onFetch: () => h.stop()}); await h.click('send-batch');
    assert.equal(h.calls.length, 1); assert.ok(!('signal' in h.calls[0].settings)); assert.match(h.report.textContent, /Stopped after current step/);
});
test('foreign endpoints receive neither campaign data nor credentials', async () => {
    const h = harness([], {foreign: true}); await h.click('queue'); assert.equal(h.calls.length, 0); assert.equal(h.panel.hidden, true);
});
test('authorization failure or malformed response never retries automatically', async () => {
    for (const response of [{success: false}, {success: true, data: {revision: 'r2'}}]) {
        const h = harness([response]); await h.click('queue'); assert.equal(h.calls.length, 1); assert.equal(h.report.textContent, 'Request stopped');
    }
});
