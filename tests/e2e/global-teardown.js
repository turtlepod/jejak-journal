const { execSync } = require('child_process');
const { existsSync, readFileSync } = require('fs');
const { join } = require('path');

const WP_PATH = process.env.WP_PATH;
if (!WP_PATH) {
	throw new Error('WP_PATH environment variable is required. Set it to the absolute path of your WordPress install.');
}
const PAGES_FILE = join(__dirname, '.test-pages.json');

function wp(cmd) {
	return execSync(`wp ${cmd} --path="${WP_PATH}"`, {
		encoding: 'utf-8',
	}).trim();
}

async function globalTeardown() {
	if (!existsSync(PAGES_FILE)) {
		return;
	}

	const pageData = JSON.parse(readFileSync(PAGES_FILE, 'utf-8'));

	for (const key of ['journal']) {
		const id = pageData[`${key}_id`];
		if (id) {
			wp(`post delete ${id} --force`);
		}
	}
}

module.exports = globalTeardown;
