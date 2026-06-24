const { test, expect } = require('@playwright/test');
const { readFileSync } = require('fs');
const { join } = require('path');

const pagesFile = join(__dirname, '..', '.test-pages.json');

test.describe('Jejak Journal', () => {
	test('shortcode page renders journal interface', async ({ page }) => {
		const pages = JSON.parse(readFileSync(pagesFile, 'utf-8'));
		await page.goto(pages.journal_url);
		await expect(page.locator('.jejak-journal-app')).toBeVisible();
		await expect(page.locator('.jejak-header')).toBeVisible();
		await expect(page.locator('.jejak-title')).toBeVisible();
	});
});
