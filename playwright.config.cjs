const { defineConfig } = require('@playwright/test');
const os = require('node:os');
const path = require('node:path');
module.exports = defineConfig({
  testDir: './tests/Browser',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  expect: { timeout: 20000 },
  timeout: 120000,
  outputDir: path.join(os.tmpdir(), 'cor-mgmt-browser-results'),
  reporter: 'list',
  use: { browserName: 'chromium', channel: process.env.COR_TEST_BROWSER_CHANNEL || undefined, headless: true, trace: 'off' },
});
