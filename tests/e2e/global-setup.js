const { chromium } = require('@playwright/test');
const { execSync } = require('child_process');
const { mkdirSync, writeFileSync } = require('fs');
const { join } = require('path');

const WP_PATH = process.env.WP_PATH;
if (!WP_PATH) {
	throw new Error('WP_PATH environment variable is required. Set it to the absolute path of your WordPress install.');
}
const BASE_URL = process.env.WP_BASE_URL ?? 'https://jejak.ddev.site';
const TEST_USER = process.env.WP_TEST_USER ?? 'test';
const TEST_PASS = process.env.WP_TEST_PASS ?? 'test123';

const PAGES_FILE = join(__dirname, '.test-pages.json');
const AUTH_DIR = join(__dirname, '.auth');

/** Run a WP-CLI command and return trimmed stdout. */
function wp(cmd) {
	return execSync(`wp ${cmd} --path="${WP_PATH}"`, {
		encoding: 'utf-8',
	}).trim();
}

const SHORTCODES = {
	journal: '[jejak-journal]',
};

async function globalSetup() {
	// -----------------------------------------------------------------------
	// 1. Create one test page per shortcode
	// -----------------------------------------------------------------------
	const pageData = {};

	// Resolve the author ID from the configured test user login.
	const rawAuthorId = wp(`user get "${TEST_USER}" --field=ID`);
	const authorId = Number.parseInt(rawAuthorId, 10);
	if (!Number.isFinite(authorId) || authorId <= 0) {
		throw new Error(`Could not resolve a valid user ID for WP_TEST_USER="${TEST_USER}". WP-CLI returned: "${rawAuthorId}"`);
	}

	for (const [key, shortcode] of Object.entries(SHORTCODES)) {
		const title = `E2E Jejak ${key}`;

		// Delete any stale page with the same title first.
		const existing = wp(
			`post list --post_type=page --post_status=any --title="${title}" --fields=ID --format=ids`
		);
		if (existing) {
			wp(`post delete ${existing} --force`);
		}

		const id = wp(
			`post create --post_type=page --post_status=publish --post_title="${title}" --post_content="${shortcode}" --post_author=${authorId.toString()} --porcelain`
		);
		const url = wp(`post get ${id} --field=guid`);

		pageData[`${key}_id`] = id;
		pageData[`${key}_url`] = url;
	}

	writeFileSync(PAGES_FILE, JSON.stringify(pageData, null, 2));

	// -----------------------------------------------------------------------
	// 2. Save authenticated browser storage state for the test user
	// -----------------------------------------------------------------------
	mkdirSync(AUTH_DIR, { recursive: true });

	const browser = await chromium.launch();
	const context = await browser.newContext({ ignoreHTTPSErrors: true });
	const page = await context.newPage();

	await page.goto(`${BASE_URL}/wp-login.php`);
	await page.fill('#user_login', TEST_USER);
	await page.fill('#user_pass', TEST_PASS);
	await page.click('#wp-submit');
	await page.waitForURL(`${BASE_URL}/wp-admin/**`);

	await context.storageState({ path: join(AUTH_DIR, 'user.json') });
	await browser.close();
}

module.exports = globalSetup;
