/**
 * Log in once as administrator, editor and subscriber and save the sessions for the tests.
 */
import { chromium, type FullConfig, type Page } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

/**
 * The users which are used by the tests (editor and subscriber are created by tests/e2e/setup.php).
 *
 * The editor may edit all pages, but has not the capabilities of Easy Language (only administrators and
 * the role "Editor for Easy Language" have them).
 */
export const users = {
	admin: { login: 'admin', password: 'password' },
	editor: { login: 'editor', password: 'editor' },
	subscriber: { login: 'subscriber', password: 'subscriber' },
};

/**
 * Return the path of the saved session of a user.
 *
 * @param user The user.
 */
export function authFile( user: keyof typeof users ): string {
	return path.join( __dirname, '.auth', `${ user }.json` );
}

/**
 * How long the login may take (Playground could be slow on the first request to wp-admin, e.g. on CI runners with few CPUs).
 */
const loginTimeout = 120_000;

/**
 * How often the login is tried, before the setup fails (the global setup is not repeated by the retries of Playwright).
 */
const loginAttempts = 2;

/**
 * Log in the given user on the given page.
 *
 * Waits for the redirect to wp-admin together with the click, so a fast redirect could not be missed.
 *
 * @param page The page.
 * @param user The user.
 */
async function login( page: Page, user: keyof typeof users ): Promise< void > {
	for ( let attempt = 1; attempt <= loginAttempts; attempt++ ) {
		try {
			await page.goto( '/wp-login.php', { timeout: loginTimeout } );
			await page.locator( '#user_login' ).fill( users[ user ].login );
			await page.locator( '#user_pass' ).fill( users[ user ].password );
			await Promise.all( [
				page.waitForURL( /wp-admin/, { timeout: loginTimeout } ),
				page.locator( '#wp-submit' ).click(),
			] );
			return;
		} catch ( error ) {
			// fail with the error of the last attempt.
			if ( attempt === loginAttempts ) {
				throw error;
			}
			console.warn( `Login of "${ user }" failed in attempt ${ attempt }, trying again.` );
		}
	}
}

export default async function globalSetup( config: FullConfig ): Promise< void > {
	const { baseURL, launchOptions } = config.projects[ 0 ].use;
	fs.mkdirSync( path.join( __dirname, '.auth' ), { recursive: true } );

	const browser = await chromium.launch( launchOptions );
	try {
		for ( const user of Object.keys( users ) as Array< keyof typeof users > ) {
			const page = await browser.newPage( { baseURL } );
			await login( page, user );
			await page.context().storageState( { path: authFile( user ) } );
			await page.close();
		}
	} finally {
		await browser.close();
	}
}
