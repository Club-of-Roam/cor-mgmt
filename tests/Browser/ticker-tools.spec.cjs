const {test, expect} = require('@playwright/test');
const path = require('node:path');

const source = path.resolve(__dirname, '../../js/cor-mgmt-ticker-tools.js');
const origin = 'https://ticker.example.test';
const state = (phase, cursor, verified = 0) => ({state: phase, cursor, verified, count: 2, max: 500, shape: 'test-source', issues: 3});
const html = (value, success = false) => `<!doctype html><html lang="en"><title>Ticker fixture</title><body>
${success ? '<div data-ticker-success>Completed</div>' : ''}
<pre data-ticker-state>${JSON.stringify(value)}</pre>
<form method="post" data-ticker-tools data-copy-text="Copy" data-verify-text="Verify" data-issues-text="Notices"
data-stopped-text="Paused" data-stopping-text="Stopping" data-complete-text="Complete" data-error-text="Reload before resuming">
<input type="hidden" name="cor_mgmt_nonce" value="synthetic-nonce">
<p data-progress role="status" aria-live="polite" hidden><span class="spinner"></span><strong data-progress-text></strong></p>
<p data-transfer-meter hidden><progress data-transfer-progress></progress></p>
<button name="ticker_action" value="step">Single step</button>
<button type="button" data-transfer-auto hidden>Resume automatic processing</button>
<button type="button" data-transfer-stop hidden>Stop after current step</button>
</form></body></html>`;

test('ticker automation uses real POST forms and parses responses through copy and verification', async ({page}) => {
    const steps = [state('verify', 0), state('verify', 100, 1), state('native', 500, 2)];
    let requests = 0;
    await page.route(`${origin}/**`, async route => {
        const request = route.request();
        if (request.method() !== 'POST') return route.fulfill({contentType: 'text/html', body: html(state('copy', 100))});
        expect(request.postData()).toContain('synthetic-nonce');
        expect(request.postData()).toContain('name="ticker_action"');
        expect(request.postData()).toContain('step');
        expect(steps.length).toBeGreaterThan(0);
        requests++;
        await route.fulfill({contentType: 'text/html', body: html(steps.shift(), true)});
    });
    await page.goto(origin);
    await page.addScriptTag({path: source});
    expect(requests).toBe(0);
    await page.getByRole('button', {name: 'Resume automatic processing'}).click();
    await expect(page.getByRole('status')).toHaveText('Complete');
    expect(requests).toBe(3);
    await expect(page.locator('[data-transfer-progress]')).toHaveAttribute('value', '2');
    await expect(page.getByRole('button', {name: 'Resume automatic processing'})).toBeDisabled();
});

test('ticker stop waits for the in-flight browser request and resume uses saved progress', async ({page}) => {
    let release;
    let entered;
    const first = new Promise(resolve => { entered = resolve; });
    const gate = new Promise(resolve => { release = resolve; });
    let requests = 0;
    const steps = [state('copy', 200), state('verify', 0), state('native', 500, 2)];
    await page.route(`${origin}/**`, async route => {
        if (route.request().method() !== 'POST') return route.fulfill({contentType: 'text/html', body: html(state('copy', 100))});
        requests++;
        if (requests === 1) { entered(); await gate; }
        await route.fulfill({contentType: 'text/html', body: html(steps.shift(), true)});
    });
    await page.goto(origin);
    await page.addScriptTag({path: source});
    const resume = page.getByRole('button', {name: 'Resume automatic processing'});
    await resume.click();
    await first;
    await expect(page.getByRole('status')).toHaveText('Copy: 100 / 500 · Notices: 3');
    await page.getByRole('button', {name: 'Stop after current step'}).click();
    await expect(page.getByRole('status')).toHaveText('Stopping');
    release();
    await expect(page.getByRole('status')).toHaveText('Paused');
    expect(requests).toBe(1);
    await expect(resume).toBeEnabled();
    await expect(page.locator('[data-ticker-state]')).toContainText('200');
    await resume.click();
    await expect(page.getByRole('status')).toHaveText('Complete');
    expect(requests).toBe(3);
});
