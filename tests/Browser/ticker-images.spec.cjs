/* global document, innerWidth */
const {test, expect} = require('@playwright/test');
const path = require('node:path');

test('moderation previews preserve proportions and fit desktop and narrow screens', async ({page}) => {
    await page.setContent('<!doctype html><html lang="en"><title>Ticker images</title><body></body></html>');
    await page.addStyleTag({path: path.resolve(__dirname, '../../css/cor-mgmt-ticker.css')});
    const sizes = [[1600, 1200], [1200, 1600], [120, 90]];
    for (const [width, height] of sizes) {
        const src = 'data:image/svg+xml,' + encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}"><rect width="100%" height="100%" fill="blue"/></svg>`);
        await page.evaluate(({src}) => {
            const article = document.createElement('article');
            article.className = 'cor-ticker-post cor-ticker-post--moderation';
            const link = document.createElement('a');
            link.className = 'cor-ticker-image-link';
            link.href = src;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            const image = document.createElement('img');
            image.src = src;
            image.alt = 'Synthetic ticker image';
            link.append(image);
            const label = document.createElement('span');
            label.textContent = 'Open full-size image';
            link.append(label);
            article.append(link);
            document.body.append(article);
        }, {src});
    }
    await expect(page.locator('img')).toHaveCount(3);
    await page.waitForFunction(() => [...document.images].every(image => image.complete && image.naturalWidth > 0));
    for (const width of [1000, 320]) {
        await page.setViewportSize({width, height: 800});
        for (let index = 0; index < sizes.length; index++) {
            const image = page.locator('img').nth(index);
            const box = await image.boundingBox();
            expect(box.width).toBeLessThanOrEqual(Math.min(360, sizes[index][0]));
            expect(box.height).toBeLessThanOrEqual(Math.min(280, sizes[index][1]));
            expect(box.width / box.height).toBeCloseTo(sizes[index][0] / sizes[index][1], 1);
            expect(box.x + box.width).toBeLessThanOrEqual(width);
        }
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    }
    const links = page.getByRole('link', {name: /Open full-size image/});
    await expect(links).toHaveCount(3);
    await expect(links.first()).toHaveAttribute('target', '_blank');
    await page.keyboard.press('Tab');
    await expect(links.first()).toBeFocused();
});
