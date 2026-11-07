/**
 * Configuration of the E2E tests with Playwright.
 *
 * The tests run against WordPress Playground, which is started by Playwright itself
 * (see "webServer"). The ChatGPT API is replaced by a mock (tests/e2e/mu-plugins), which
 * answers with a predictable simplification. No account at OpenAI is needed.
 *
 * Run: npx playwright test
 *
 * Optional environment variables:
 * - EASY_LANGUAGE_E2E_PORT: the port for Playground (default 9400).
 * - EASY_LANGUAGE_E2E_PLAYGROUND_ARGS: additional arguments for the Playground CLI, e.g. to use a local copy of
 *   WordPress instead of downloading it: "--mount-before-install=/path/to/wordpress:/wordpress --wordpress-install-mode=install-from-existing-files".
 *   Use a fresh copy for every run, as Playground installs WordPress in it.
 * - EASY_LANGUAGE_E2E_CHROMIUM: path to a Chromium binary which should be used instead of the one installed by Playwright.
 *
 * Playground uses tmp/e2e in the project as home and temp folder (for its cache), nothing is written outside the project.
 */
import { defineConfig, devices } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const port = Number( process.env.EASY_LANGUAGE_E2E_PORT || 9400 );

// Playground writes its cache to <home>/.wordpress-playground and temporary files to the temp folder of the system.
// Use a folder inside the project for both, so no folder outside of it is needed (e.g. on servers without a writable home).
const tmpDir = path.join( __dirname, 'tmp', 'e2e' );
fs.mkdirSync( tmpDir, { recursive: true } );
const baseURL = `http://127.0.0.1:${ port }`;

const playgroundArgs = [
	'server',
	`--port=${ port }`,
	'--php=8.3',
	'--blueprint=./tests/e2e/blueprint.json',
	'--mount=.:/wordpress/wp-content/plugins/easy-language',
	'--mount=./tests/e2e/mu-plugins:/wordpress/wp-content/mu-plugins',
	process.env.EASY_LANGUAGE_E2E_PLAYGROUND_ARGS || '',
].join( ' ' );

export default defineConfig( {
	testDir: './tests/e2e/specs',
	globalSetup: './tests/e2e/global-setup.ts',

	// one WordPress for all tests: run them one after another.
	fullyParallel: false,
	workers: 1,

	// fail the build on CI if test.only is left in the source code.
	forbidOnly: !! process.env.CI,
	retries: process.env.CI ? 1 : 0,

	// Playground is slower than a normal WordPress.
	timeout: 90_000,
	expect: { timeout: 20_000 },

	reporter: process.env.CI ? [ [ 'list' ], [ 'html', { open: 'never' } ] ] : [ [ 'list' ] ],

	use: {
		baseURL,
		storageState: './tests/e2e/.auth/admin.json',
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		launchOptions: process.env.EASY_LANGUAGE_E2E_CHROMIUM ? { executablePath: process.env.EASY_LANGUAGE_E2E_CHROMIUM } : {},
	},

	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],

	webServer: {
		// "--no": use the version from package-lock.json, never download another one.
		command: `npx --no -- @wp-playground/cli ${ playgroundArgs }`,
		url: `${ baseURL }/wp-login.php`,
		// downloading WordPress and running the setup of the test data takes a while.
		timeout: 300_000,
		reuseExistingServer: ! process.env.CI,
		stdout: 'pipe',
		stderr: 'pipe',
		env: {
			HOME: tmpDir,
			USERPROFILE: tmpDir,
			TMPDIR: tmpDir,
			TMP: tmpDir,
			TEMP: tmpDir,
		},
	},
} );
