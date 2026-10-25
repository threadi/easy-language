/**
 * Tests for the settings of the plugin in the backend.
 */
import { test, expect } from '@playwright/test';
import { getRestNonce, openPage, settingsUrl, simplifiedMarker } from './helpers';

test.describe( 'Settings', () => {
	test( 'all tabs of the settings load without errors', async ( { page } ) => {
		await openPage( page, settingsUrl() );

		// collect the tabs of the settings page (the help tab links to wordpress.org).
		const tabs = await page.locator( 'a.nav-tab[href*="page=easy_language_settings"]' ).evaluateAll( ( links ) =>
			[ ...new Set( links.map( ( link ) => new URL( ( link as HTMLAnchorElement ).href ).searchParams.get( 'tab' ) ) ) ]
		);
		expect( tabs ).toEqual( expect.arrayContaining( [ 'basic', 'chatgpt', 'easy_language_general', 'simplified_texts', 'icons', 'advanced', 'api_logs', 'logs' ] ) );

		for ( const tab of tabs ) {
			await openPage( page, settingsUrl( tab ?? '' ) );
		}
	} );

	test( 'ChatGPT is the active API', async ( { page } ) => {
		await openPage( page, settingsUrl( 'basic' ) );

		await expect( page.locator( 'input[name="easy_language_api"][value="chatgpt"]' ) ).toBeChecked();
		await expect( page.locator( '#easy-language-active-api' ) ).toHaveText( 'Active' );
	} );

	test( 'the API key is never shown in the settings', async ( { page } ) => {
		await openPage( page, settingsUrl( 'chatgpt' ) );

		// the field only shows a mask, which keeps the saved key on saving.
		const field = page.locator( 'input[name="easy_language_chatgpt_api_key"]' );
		await expect( field ).toHaveAttribute( 'type', 'password' );
		await expect( field ).toHaveValue( '********' );
		expect( await page.content() ).not.toContain( 'test_api_key' );
	} );

	test( 'the active languages can be chosen for ChatGPT', async ( { page } ) => {
		await openPage( page, settingsUrl( 'chatgpt' ) );

		// both languages are enabled by tests/e2e/setup.php.
		for ( const language of [ 'de_LS', 'de_EL' ] ) {
			await expect( page.locator( `input[type="checkbox"][name="easy_language_chatgpt_target_languages[${ language }]"]` ) ).toBeChecked();
		}
	} );

	test( 'the simplified texts of a page are listed', async ( { page } ) => {
		await openPage( page, settingsUrl( 'simplified_texts', 'simplified_texts_in_use' ) );

		// the texts of the page "E2E Original", which has been simplified by tests/e2e/setup.php.
		const table = page.locator( 'table.wp-list-table' );
		await expect( table ).toContainText( 'Das ist ein komplizierter Text mit vielen Fremdwörtern.' );
		await expect( table ).toContainText( `${ simplifiedMarker }Das ist ein komplizierter Text mit vielen Fremdwörtern.` );
		await expect( table ).toContainText( 'Leichte Sprache' );
	} );

	test( 'the texts to simplify can be listed', async ( { page } ) => {
		await openPage( page, settingsUrl( 'simplified_texts', 'simplified_texts_to_simplify' ) );

		await expect( page.locator( 'table.wp-list-table' ) ).toBeVisible();
	} );
} );

test.describe( 'Site Health', () => {
	test( 'the check of the API reports the configured ChatGPT', async ( { page } ) => {
		await page.goto( '/wp-admin/profile.php' );
		const nonce = await getRestNonce( page );

		// this endpoint is used by the test of the plugin in Tools > Site Health.
		const response = await page.request.get( '/wp-json/easy-language/v1/api_check/', { headers: { 'X-WP-Nonce': nonce } } );
		expect( response.status() ).toBe( 200 );

		const result = ( await response.json() ) as { status: string; description: string };
		expect( result.status ).toBe( 'good' );
		expect( result.description ).toContain( 'ChatGpt' );
	} );
} );
