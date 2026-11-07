/**
 * Tests that only users with the capabilities of Easy Language can simplify, and that the API key is never exposed.
 *
 * Uses the page "E2E Not Simplified", which must never get a simplification.
 */
import { test, expect, type Browser, type Page } from '@playwright/test';
import { authFile } from '../global-setup';
import { getRestNonce, switcherLinks } from './helpers';

/**
 * Return the ID of the page "E2E Not Simplified".
 *
 * @param page The page.
 */
async function getPageId( page: Page ): Promise< number > {
	const response = await page.request.get( '/wp-json/wp/v2/pages?slug=e2e-not-simplified&_fields=id' );
	const [ object ] = ( await response.json() ) as Array< { id: number } >;
	return object.id;
}

/**
 * Check as visitor that the page "E2E Not Simplified" still has no simplification.
 *
 * @param browser The browser.
 * @param baseURL The URL of the website.
 */
async function expectNotSimplified( browser: Browser, baseURL: string | undefined ): Promise< void > {
	const visitor = await browser.newPage( { baseURL } );
	try {
		await visitor.goto( '/e2e-not-simplified/' );
		await expect( switcherLinks( visitor ) ).toHaveText( [ 'German' ] );
	} finally {
		await visitor.close();
	}
}

// users without the capabilities of Easy Language: the editor may edit all pages, the subscriber none.
for ( const user of [ 'editor', 'subscriber' ] as const ) {
	test.describe( `Users without simplification rights (${ user })`, () => {
		test.use( { storageState: authFile( user ) } );

		test( 'cannot add a simplification via AJAX', async ( { page, browser, baseURL } ) => {
			await page.goto( '/wp-admin/profile.php' );

			// the nonce is available in the backend for every user, the capability must be checked anyway.
			const vars = await page.evaluate( () => ( window as any ).easyLanguageSimplificationJsVars as { add_simplification_nonce: string } | undefined );
			expect( vars?.add_simplification_nonce ).toBeTruthy();

			const response = await page.request.post( '/wp-admin/admin-ajax.php', {
				form: {
					action: 'easy_language_add_simplification_object',
					id: String( await getPageId( page ) ),
					type: 'page',
					language: 'de_EL',
					nonce: vars!.add_simplification_nonce,
				},
			} );
			expect( await response.text() ).not.toContain( '"status":"ok"' );

			await expectNotSimplified( browser, baseURL );
		} );

		test( 'cannot load the language options of a page', async ( { page } ) => {
			await page.goto( '/wp-admin/profile.php' );
			const nonce = await getRestNonce( page );

			const response = await page.request.post( `/wp-json/easy-language/v1/language-options/${ await getPageId( page ) }`, { headers: { 'X-WP-Nonce': nonce } } );
			expect( response.status() ).toBe( 403 );
		} );

		test( 'cannot use the abilities', async ( { page } ) => {
			await page.goto( '/wp-admin/profile.php' );
			const nonce = await getRestNonce( page );

			const response = await page.request.get( '/wp-json/wp-abilities/v1/abilities/easy-language/get-configuration/run', { headers: { 'X-WP-Nonce': nonce } } );
			expect( response.status() ).toBe( 403 );
		} );

		test( 'cannot use the API check', async ( { page } ) => {
			await page.goto( '/wp-admin/profile.php' );
			const nonce = await getRestNonce( page );

			const response = await page.request.get( '/wp-json/easy-language/v1/api_check/', { headers: { 'X-WP-Nonce': nonce } } );
			expect( response.status() ).toBe( 403 );
		} );
	} );
}

test.describe( 'Visitors', () => {
	test.use( { storageState: { cookies: [], origins: [] } } );

	test( 'cannot add a simplification via AJAX', async ( { request, browser, baseURL } ) => {
		const response = await request.post( '/wp-admin/admin-ajax.php', {
			form: { action: 'easy_language_add_simplification_object', id: '1', type: 'page', language: 'de_EL', nonce: 'invalid' },
		} );
		expect( await response.text() ).not.toContain( '"status":"ok"' );

		await expectNotSimplified( browser, baseURL );
	} );

	test( 'cannot use the REST endpoints of the plugin', async ( { request } ) => {
		for ( const [ method, path ] of [
			[ 'post', '/wp-json/easy-language/v1/language-options/1' ],
			[ 'get', '/wp-json/easy-language/v1/api_check/' ],
			[ 'get', '/wp-json/easy-language/v1/switcher-objects' ],
		] as const ) {
			const response = await request[ method ]( path );
			expect( response.status(), `${ method } ${ path }` ).toBe( 401 );
		}
	} );
} );

test.describe( 'API key', () => {
	test( 'is not part of the settings in the REST API', async ( { page } ) => {
		await page.goto( '/wp-admin/profile.php' );
		const nonce = await getRestNonce( page );

		const response = await page.request.get( '/wp-json/wp/v2/settings', { headers: { 'X-WP-Nonce': nonce } } );
		expect( response.status() ).toBe( 200 );
		const settings = ( await response.json() ) as Record< string, unknown >;

		// the settings of the plugin are available, but not the key.
		expect( settings.easy_language_api ).toBe( 'chatgpt' );
		expect( Object.keys( settings ) ).not.toContain( 'easy_language_chatgpt_api_key' );
		expect( JSON.stringify( settings ) ).not.toContain( 'test_api_key' );
	} );

	test( 'is not part of the configuration returned by the abilities', async ( { page } ) => {
		await page.goto( '/wp-admin/profile.php' );
		const nonce = await getRestNonce( page );

		const response = await page.request.get( '/wp-json/wp-abilities/v1/abilities/easy-language/get-configuration/run', { headers: { 'X-WP-Nonce': nonce } } );
		expect( response.status() ).toBe( 200 );

		const configuration = await response.json();
		expect( configuration.api ).toMatchObject( { name: 'chatgpt', configured: true } );
		expect( JSON.stringify( configuration ) ).not.toContain( 'test_api_key' );
	} );
} );
