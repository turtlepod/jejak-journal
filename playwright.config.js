const { defineConfig, devices } = require('@playwright/test');

module.exports = defineConfig({
	testDir: './tests/e2e/specs',
	globalSetup: require.resolve('./tests/e2e/global-setup'),
	globalTeardown: require.resolve('./tests/e2e/global-teardown'),
	fullyParallel: false,
	forbidOnly: !!process.env.CI,
	retries: process.env.CI ? 2 : 0,
	reporter: [['html', { outputFolder: 'tests/e2e/report' }]],
	use: {
		baseURL: process.env.WP_BASE_URL ?? 'https://jejak.ddev.site',
		ignoreHTTPSErrors: true,
		storageState: 'tests/e2e/.auth/user.json',
		screenshot: 'only-on-failure',
		video: 'retain-on-failure',
	},
	projects: [
		{
			name: 'chromium',
			use: {
				...devices['Desktop Chrome'],
				ignoreHTTPSErrors: true,
			},
		},
	],
});
