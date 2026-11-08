const { test, expect } = require('@playwright/test');
const { default: AxeBuilder } = require('@axe-core/playwright');
const { spawn, execFileSync } = require('node:child_process');
const { randomBytes } = require('node:crypto');
const net = require('node:net');
const path = require('node:path');
let base, token, server, env;
const root = path.resolve(__dirname, '../..');
const tool = path.join(root, 'tools/communication-browser.php');
const php = process.env.COR_TEST_PHP || 'php';
test.beforeAll(async () => {
  if (!/^cor_mgmt_test_/.test(process.env.COR_TEST_DB_NAME || '')) throw new Error('A guarded synthetic fixture is required.');
  const socket = net.createServer();
  await new Promise(resolve => socket.listen(0, '127.0.0.1', resolve));
  const port = socket.address().port;
  await new Promise(resolve => socket.close(resolve));
  base = `http://127.0.0.1:${port}`;
  token = randomBytes(32).toString('hex');
  env = { ...process.env, COR_TEST_BROWSER_TOKEN: token, COR_TEST_BROWSER_BASE: base, XDEBUG_MODE: 'off' };
  execFileSync(php, [tool, 'prepare'], { cwd: root, env, timeout: 90000 });
  server = spawn(php, ['-S', `127.0.0.1:${port}`, tool], { cwd: root, env, windowsHide: true, stdio: ['ignore', 'ignore', 'pipe'] });
  let error = '';
  server.stderr.on('data', data => { error += data.toString(); });
  const until = Date.now() + 30000;
  while (Date.now() < until) {
    try { const response = await fetch(base); if (response.status === 403) return; } catch { /* Server is starting. */ }
    if (server.exitCode !== null) throw new Error(error);
    await new Promise(resolve => setTimeout(resolve, 100));
  }
  throw new Error('Synthetic browser server did not start.');
});
test.afterAll(async () => {
  if (server) { server.kill(); await new Promise(resolve => server.once('exit', resolve)); }
  if (env) execFileSync(php, [tool, 'cleanup'], { cwd: root, env, timeout: 90000 });
});
async function identity(context, actor) {
  await context.addCookies([{ name: 'cor_test_browser', value: token, url: base }, { name: 'cor_test_actor', value: actor, url: base }]);
  await context.route('**/*', route => new URL(route.request().url()).origin === base ? route.continue() : route.abort());
}
async function axe(page) {
  const results = await new AxeBuilder({ page }).withTags(['wcag2a','wcag2aa','wcag21a','wcag21aa']).analyze();
  expect(results.violations.map(v => ({ id: v.id, nodes: v.nodes.map(n => n.target) }))).toEqual([]);
}
test('preview, snapshot, queue and pause a campaign without sending', async ({ page, context }) => {
  await identity(context, 'admin');
  await page.goto(base + '/mail');
  await page.getByLabel('Subject', { exact: true }).fill('Browser campaign');
  await page.getByLabel('Message', { exact: true }).fill('Hello %name%');
  await page.getByRole('button', { name: 'Preview audience and messages' }).click();
  await expect(page.getByRole('heading', { name: 'Preview', exact: true })).toBeVisible();
  await expect(page.getByText('Recipients: 1', { exact: true })).toBeVisible();
  await axe(page);
  await page.getByRole('button', { name: 'Create reviewed campaign without sending' }).click();
  await expect(page.getByText('Server delivery is disabled. No email can be sent.', { exact: true })).toBeVisible();
  await page.getByRole('button', { name: 'Prepare campaign in steps', exact: true }).click();
  await expect(page.locator('[data-batch-report]')).toContainText('Step processing finished');
  await page.getByRole('link', { name: 'Reload campaign list' }).click();
  await expect(page.locator('summary').filter({ hasText: 'pending' })).toHaveCount(1);
  await page.getByRole('button', { name: 'Release campaign', exact: true }).click();
  await page.getByRole('button', { name: 'Pause campaign', exact: true }).click();
  await expect(page.getByRole('button', { name: 'Release campaign', exact: true })).toBeVisible();
  await expect(page.getByRole('button', { name: /^Send/ })).toHaveCount(0);
  await axe(page);
});
test('a subscriber cannot use mail administration or moderation', async ({ page, context }) => {
  await identity(context, 'member');
  await page.goto(base + '/mail');
  await expect(page.getByText('Not permitted.', { exact: true })).toBeVisible();
  await expect(page.locator('form')).toHaveCount(0);
  await page.goto(base + '/moderation');
  await expect(page.getByText('Not permitted.', { exact: true })).toBeVisible();
});
test('notice board supports keyboard create, edit, delete and rejects stale replay', async ({ page, context }) => {
  await identity(context, 'member');
  await page.goto(base + '/board');
  const field = page.getByLabel('Your message (empty text deletes it)');
  await field.fill('Synthetic public notice');
  const stale = await page.locator('[name=revision]').inputValue();
  await field.press('Tab');
  await expect(page.getByRole('button', { name: 'Save message' })).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(page.getByRole('status')).toContainText('Message saved');
  await expect(field).toHaveValue('Synthetic public notice');
  await axe(page);
  await field.fill('Updated public notice');
  await page.getByRole('button', { name: 'Save message' }).click();
  await expect(field).toHaveValue('Updated public notice');
  await page.locator('[name=revision]').evaluate((el, revision) => { el.value = revision; }, stale);
  await field.fill('Stale overwrite');
  await page.getByRole('button', { name: 'Save message' }).click();
  await expect(page.getByRole('alert')).toContainText('rejected');
  await expect(field).toHaveValue('Updated public notice');
  await field.fill('');
  await page.getByRole('button', { name: 'Save message' }).click();
  await expect(field).toHaveValue('');
  await axe(page);
});
test('moderation and read-only storage report are accessible', async ({ page, context }) => {
  await identity(context, 'member');
  await page.goto(base + '/board');
  await page.getByLabel('Your message (empty text deletes it)').fill('Notice for moderation');
  await page.getByRole('button', { name: 'Save message' }).click();
  await expect(page.getByRole('status')).toContainText('Message saved');
  await identity(context, 'admin');
  await page.goto(base + '/moderation');
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('HitchMate XChange moderation');
  await page.locator('summary').first().click();
  await page.getByLabel('Your message (empty text deletes it)').fill('Moderated notice');
  await page.getByRole('button', { name: 'Save message' }).click();
  await page.locator('summary').first().click();
  await expect(page.getByLabel('Your message (empty text deletes it)')).toHaveValue('Moderated notice');
  await axe(page);
  await page.getByLabel('Your message (empty text deletes it)').fill('');
  await page.getByRole('button', { name: 'Save message' }).click();
  await expect(page.getByText(/^#\d+ · deleted/).first()).toBeVisible();
  await page.goto(base + '/report');
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Communication storage report');
  await expect(page.getByRole('table')).toBeVisible();
  await axe(page);
});
