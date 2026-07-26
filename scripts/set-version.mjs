#!/usr/bin/env node
/**
 * Update the plugin version across all relevant files.
 *
 * Usage: npm run set-version 1.2.3
 */
import { readFileSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');

const version = process.argv[2];

if (!version || !/^\d+\.\d+\.\d+(-[0-9A-Za-z.-]+)?$/.test(version)) {
	console.error('Usage: npm run set-version <version>  (e.g. 0.8.0)');
	process.exit(1);
}

/** @type {Array<{ file: string, replacements: Array<[RegExp, string]> }>} */
const targets = [
	{
		file: 'jejak-journal.php',
		replacements: [
			[/^(\s*\*\s*Version:\s*).*$/m, `$1${version}`],
			[/(define\(\s*'JEJAK_JOURNAL_VERSION',\s*')[^']*('\s*\))/, `$1${version}$2`],
		],
	},
	{
		file: 'readme.txt',
		replacements: [[/^(Stable tag:\s*).*$/m, `$1${version}`]],
	},
];

for (const { file, replacements } of targets) {
	const path = join(root, file);
	let contents = readFileSync(path, 'utf8');
	for (const [pattern, replacement] of replacements) {
		if (!pattern.test(contents)) {
			console.error(`Pattern not found in ${file}: ${pattern}`);
			process.exit(1);
		}
		contents = contents.replace(pattern, replacement);
	}
	writeFileSync(path, contents);
	console.log(`Updated ${file}`);
}

// package.json
const pkgPath = join(root, 'package.json');
const pkg = JSON.parse(readFileSync(pkgPath, 'utf8'));
pkg.version = version;
writeFileSync(pkgPath, `${JSON.stringify(pkg, null, 2)}\n`);
console.log('Updated package.json');

console.log(`\nVersion set to ${version}`);
