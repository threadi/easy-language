/**
 * Helpers for the E2E tests.
 */
import { expect, type Locator, type Page, type Response } from '@playwright/test';

/**
 * The marker the API mock adds to every simplified text (see tests/e2e/mu-plugins/easy-language-e2e-api-mock.php).
 */
export const simplifiedMarker = 'Leicht gesagt: ';

/**
 * The URL of the settings page of the plugin.
 *
 * @param tab    Optional the tab to open.
 * @param subtab Optional the sub-tab to open.
 */
export function settingsUrl( tab = '', subtab = '' ): string {
	return '/wp-admin/options-general.php?page=easy_language_settings' + ( tab ? `&tab=${ tab }` : '' ) + ( subtab ? `&subtab=${ subtab }` : '' );
}

/**
 * Check that the given page does not show any PHP error.
 *
 * @param page The page.
 */
export async function expectNoPhpErrors( page: Page ): Promise< void > {
	const html = await page.content();
	for ( const marker of [ 'Fatal error', 'Parse error', 'Uncaught', '<b>Warning</b>:', '<b>Notice</b>:', '<b>Deprecated</b>:', 'There has been a critical error' ] ) {
		expect( html, `page contains "${ marker }"` ).not.toContain( marker );
	}
}

/**
 * Open a URL, check the status code and that no PHP error is shown.
 *
 * @param page The page.
 * @param url  The URL.
 */
export async function openPage( page: Page, url: string ): Promise< Response > {
	const response = await page.goto( url );
	expect( response, `no response for ${ url }` ).not.toBeNull();
	expect( response!.status(), `status of ${ url }` ).toBeLessThan( 400 );
	await expectNoPhpErrors( page );
	return response!;
}

/**
 * Return a nonce for the REST API of the logged-in user of the page.
 *
 * @param page The page with a logged-in user.
 */
export async function getRestNonce( page: Page ): Promise< string > {
	const response = await page.request.get( '/wp-admin/admin-ajax.php?action=rest-nonce' );
	expect( response.ok() ).toBeTruthy();
	return ( await response.text() ).trim();
}

/**
 * Return the row of a page in the list of pages in the backend.
 *
 * @param page  The page.
 * @param title The title of the page.
 */
export function pageRow( page: Page, title: string ): Locator {
	return page.locator( '#the-list tr' ).filter( { has: page.locator( 'a.row-title', { hasText: new RegExp( `^${ title }$` ) } ) } );
}

/**
 * Return the cell of a language in the row of a page in the list of pages in the backend.
 *
 * @param page     The page.
 * @param title    The title of the page.
 * @param language The language code, e.g. "de_LS".
 */
export function languageCell( page: Page, title: string, language: string ): Locator {
	return pageRow( page, title ).locator( `td.column-easy-language-${ language.toLowerCase() }` );
}

/**
 * Return the dialog of the plugin (shown by easy-dialog-for-wordpress).
 *
 * @param page The page.
 */
export function dialog( page: Page ): Locator {
	return page.locator( '.components-modal__frame' );
}

/**
 * Return the links of the language switcher in the content of a page in the frontend.
 *
 * @param page The page.
 */
export function switcherLinks( page: Page ): Locator {
	return page.locator( '.wp-block-post-content a.easy-language-switcher-title' );
}
