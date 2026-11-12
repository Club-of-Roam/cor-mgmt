const {test, expect} = require('@playwright/test');
const path = require('node:path');
const origin = 'https://events.example.test';
const html = `<!doctype html><html lang="en"><meta charset="utf-8"><title>Page picker fixture</title><body>
<form method="post" data-search-picker data-result-text="Matching pages: %d">
<p data-picker-search hidden><label for="search">Search matching pages</label><input id="search" type="search" aria-controls="pages"><span role="status" data-picker-matches></span></p>
<label for="pages">Existing WordPress page</label><select id="pages" name="page_id" required>
<option value="">Choose a matching page</option><option value="27" selected>Acceptance page #27</option>
<option value="42">Köln 2026 #42</option><option value="6">Historical page #6</option>
</select><button>Assign existing page</button></form>
<form method="post"><input type="hidden" name="page_operation" value="create"><button>Create or reuse draft</button></form>
</body></html>`;

test('page search filters names, accents and IDs without requests or automatic selection', async ({page}) => {
    let requests = 0;
    await page.route(`${origin}/**`, async route => { requests++; await route.fulfill({contentType: 'text/html; charset=utf-8', body: html}); });
    await page.goto(origin);
    await page.addScriptTag({path: path.resolve(__dirname, '../../js/cor-mgmt-page-pickers.js')});
    const search = page.getByRole('searchbox', {name: 'Search matching pages'});
    const pages = page.getByRole('combobox', {name: 'Existing WordPress page', exact: true});
    await expect(pages).toHaveValue('27');
    await search.fill('KOLN');
    await expect(page.getByRole('status')).toHaveText('Matching pages: 1');
    await expect(pages).toHaveValue('');
    await pages.selectOption('42');
    await search.fill('');
    await expect(page.getByRole('status')).toHaveText('Matching pages: 3');
    await expect(pages).toHaveValue('42');
    await search.fill('not found');
    await expect(page.getByRole('status')).toHaveText('Matching pages: 0');
    await expect(pages).toHaveValue('');
    await search.fill('#27');
    await expect(page.getByRole('status')).toHaveText('Matching pages: 1');
    await pages.selectOption('27');
    expect(requests).toBe(1);
    await page.getByRole('button', {name: 'Assign existing page', exact: true}).click();
    expect(requests).toBe(2);
});

test('native selection works without JavaScript and draft creation needs no page selection', async ({page}) => {
    let posts = 0;
    await page.route(`${origin}/**`, async route => {
        if (route.request().method() === 'POST') posts++;
        await route.fulfill({contentType: 'text/html; charset=utf-8', body: html});
    });
    await page.goto(origin);
    await expect(page.getByRole('searchbox')).toBeHidden();
    const pages = page.getByRole('combobox', {name: 'Existing WordPress page'});
    await pages.selectOption('');
    await page.getByRole('button', {name: 'Assign existing page', exact: true}).click();
    expect(posts).toBe(0);
    await page.getByRole('button', {name: 'Create or reuse draft', exact: true}).click();
    expect(posts).toBe(1);
    await pages.selectOption('6');
    await page.getByRole('button', {name: 'Assign existing page', exact: true}).click();
    expect(posts).toBe(2);
});
