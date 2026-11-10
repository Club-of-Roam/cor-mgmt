const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../js/cor-mgmt-archive.js'), 'utf8');
function harness(responses, options = {}) {
    const calls = [];
    const status = {textContent: ''};
    let stopClick;
    const stop = {disabled: true, addEventListener: (_, fn) => { stopClick = fn; }};
    const buttons = ['archive-step', 'live-step'].map(operation => ({disabled: false, dataset: {archiveRun: operation}, addEventListener(_, fn) { this.click = fn; }}));
    const form = {
        dataset: {endpoint: options.foreign ? 'https://other.invalid/ajax' : '/ajax'},
        reportValidity: () => options.confirmed !== false,
        querySelector: selector => selector === '[data-archive-stop]' ? stop : status,
        querySelectorAll: selector => selector === 'button' ? [...buttons, stop] : buttons,
    };
    class Fields extends FormData { constructor() { super(); this.set('snapshot', 'a'.repeat(32)); this.set('cor_mgmt_nonce', 'synthetic'); this.set('backup', '1'); this.set('quiesced', '1'); } }
    vm.runInNewContext(source, {
        document: {querySelectorAll: () => [form]},
        window: {location: {href: 'https://example.invalid/admin', origin: 'https://example.invalid'}}, URL, FormData: Fields,
        fetch: async (url, settings) => {
            calls.push({url, settings}); options.onFetch?.();
            const result = responses.shift();
            return {ok: result.success, json: async () => result};
        },
    });
    return {calls, status, stop: () => stopClick(), click: operation => buttons.find(button => button.dataset.archiveRun === operation).click(), buttons};
}
const result = (state, snapshot = 'a'.repeat(32)) => ({success: true, data: {state, copied: 10, snapshot}});
test('archive processing requires explicit confirmation and never starts on load', async () => {
    const h = harness([], {confirmed: false}); assert.equal(h.calls.length, 0); await h.click('archive-step'); assert.equal(h.calls.length, 0);
});
test('archive processing uses authenticated same-origin POST and stops before publication', async () => {
    const h = harness([result('copy'), result('verify'), result('ready')]); await h.click('archive-step');
    assert.equal(h.calls.length, 3);
    for (const {settings} of h.calls) {
        assert.equal(settings.method, 'POST'); assert.equal(settings.redirect, 'error'); assert.equal(settings.credentials, 'same-origin');
        assert.equal(settings.body.get('operation'), 'archive-step'); assert.equal(settings.body.get('cor_mgmt_nonce'), 'synthetic'); assert.equal(settings.body.get('backup'), '1');
    }
    assert.match(h.status.textContent, /ready/); assert.ok(h.buttons.every(button => !button.disabled));
});
test('storage processing clears the unrelated archive identity and never activates automatically', async () => {
    const h = harness([result('prepare', ''), result('ready', '')]); await h.click('live-step');
    assert.equal(h.calls.length, 2); assert.equal(h.calls[0].settings.body.get('snapshot'), ''); assert.equal(h.calls[0].settings.body.get('operation'), 'live-step');
});
test('stop waits for the current step without aborting or retrying it', async () => {
    let h; h = harness([result('copy')], {onFetch: () => h.stop()}); await h.click('archive-step'); assert.equal(h.calls.length, 1); assert.ok(!('signal' in h.calls[0].settings));
});
test('foreign endpoints never receive maintenance credentials', async () => {
    const h = harness([], {foreign: true}); await h.click('archive-step'); assert.equal(h.calls.length, 0);
});
test('authorization errors, malformed counters and wrong snapshot identities stop without retries', async () => {
    for (const response of [{success: false}, {success: true, data: {state: 'copy', copied: '10', snapshot: 'a'.repeat(32)}}, result('copy', 'b'.repeat(32))]) {
        const h = harness([response]); await h.click('archive-step'); assert.equal(h.calls.length, 1); assert.match(h.status.textContent, /stopped|Unexpected/);
    }
});
