/**
 * Tests for the settings of the plugin in the backend.
 */
import { test, expect } from '@playwright/test';
import { expectNoPhpErrors, getRestNonce, openPage, settingsUrl, simplifiedMarker } from './helpers';

test.describe( 'Settings', () => {
	test( 'all tabs of the settings load without errors', async ( { page } ) => {
		// opens every tab of the settings, which takes a while in Playground.
		test.setTimeout( 300_000 );

		const errors: string[] = [];
		page.on( 'pageerror', ( error ) => errors.push( error.message ) );

		await openPage( page, settingsUrl() );

		// collect the tabs of the settings page from the DataView (the help tab links to wordpress.org).
		const tabList = page.locator( '[role="tab"]' );
		await expect( tabList.first() ).toBeVisible();
		const tabs = await tabList.evaluateAll( ( elements ) =>
			elements.filter( ( element ) => ! element.classList.contains( 'easy-language-help-tab' ) ).map( ( element ) => element.id.replace( /^tab-panel-\d+-/, '' ) )
		);
		expect( tabs ).toEqual( expect.arrayContaining( [ 'basic', 'chatgpt', 'easy_language_general', 'simplified_texts', 'icons', 'advanced', 'api_logs', 'logs' ] ) );

		// open each tab via its URL: it must be the chosen tab and its content must not contain any error.
		for ( const tab of tabs ) {
			await openPage( page, settingsUrl( tab ) );
			await expect( page.locator( `[role="tab"][id$="-${ tab }"]` ) ).toHaveAttribute( 'aria-selected', 'true' );
			await expect( page.locator( '[role="tabpanel"]' ).first() ).toBeVisible();
			await expectNoPhpErrors( page );
		}

		expect( errors ).toEqual( [] );
	} );

	test( 'ChatGPT is the active API', async ( { page } ) => {
		await openPage( page, settingsUrl( 'basic' ) );

		await expect( page.locator( 'input[name="easy_language_api"][value="chatgpt"]' ) ).toBeChecked();

		// only the card of the active API has the badge.
		const cards = page.locator( '.esfw-cards-easy_language_api' );
		await expect( cards.locator( '.esfw-card:has(input[value="chatgpt"]) .esfw-card__badge' ) ).toHaveText( 'Active' );
		await expect( cards.locator( '.esfw-card__badge' ) ).toHaveCount( 1 );
	} );

	test( 'each API is shown as card with title, logo and description', async ( { page } ) => {
		await openPage( page, settingsUrl( 'basic' ) );

		const cards = page.locator( '.esfw-cards-easy_language_api' );
		for ( const api of [ 'capito', 'summ_ai', 'chatgpt', 'no_api' ] ) {
			const card = cards.locator( `.esfw-card:has(input[name="easy_language_api"][value="${ api }"])` );
			await expect( card ).toBeVisible();
			await expect( card.locator( '.esfw-card__label' ) ).not.toBeEmpty();
			await expect( card.locator( '.esfw-card__description' ) ).toBeVisible();

			// the description is shown as HTML, not as code.
			await expect( card.locator( '.esfw-card__description' ) ).not.toContainText( '</p>' );
		}

		// the logos of the APIs with logo.
		await expect( cards.locator( '.esfw-card:has(input[value="capito"]) img.esfw-card__icon' ) ).toBeVisible();
	} );

	test( 'only the settings tab of the active API is shown', async ( { page } ) => {
		await openPage( page, settingsUrl( 'basic' ) );

		// the tab of the active API.
		await expect( page.locator( '[role="tab"][id$="-chatgpt"]' ) ).toBeVisible();

		// the tabs of the other APIs exist, but are hidden.
		for ( const api of [ 'capito', 'summ_ai' ] ) {
			await expect( page.locator( `[role="tab"][id$="-${ api }"]` ) ).toBeHidden();
		}
	} );

	test( 'a click on a card chooses the API', async ( { page } ) => {
		await openPage( page, settingsUrl( 'basic' ) );

		// click in the corner of the card, outside of links in the description.
		const card = page.locator( '.esfw-cards-easy_language_api .esfw-card:has(input[value="summ_ai"])' );
		await card.click( { position: { x: 4, y: 4 } } );
		await expect( page.locator( 'input[name="easy_language_api"][value="summ_ai"]' ) ).toBeChecked();
		await expect( page.locator( 'input[name="easy_language_api"][value="chatgpt"]' ) ).not.toBeChecked();
	} );

	test( 'the API key is never shown in the settings', async ( { page } ) => {
		await openPage( page, settingsUrl( 'chatgpt' ) );

		// the field stays empty and only shows that a key is stored: an empty field keeps the saved key on saving.
		const field = page.getByLabel( 'ChatGPT API Key', { exact: true } );
		await expect( field ).toHaveAttribute( 'type', 'password' );
		await expect( field ).toHaveValue( '' );
		await expect( page.locator( '.esfw-password' ) ).toContainText( 'A value is stored.' );

		// neither the page nor the configuration of the DataView contains the key.
		expect( await page.content() ).not.toContain( 'test_api_key' );
		expect( JSON.stringify( await page.evaluate( () => ( window as unknown as { esfwSettingsConfig?: unknown } ).esfwSettingsConfig ?? null ) ) ).not.toContain( 'test_api_key' );
	} );

	test( 'the active languages can be chosen for ChatGPT', async ( { page } ) => {
		await openPage( page, settingsUrl( 'chatgpt' ) );

		// both languages are enabled by tests/e2e/setup.php (the DataView reads them via the REST API).
		for ( const language of [ 'Leichte Sprache', 'Einfache Sprache' ] ) {
			await expect( page.getByRole( 'checkbox', { name: language, exact: true } ) ).toBeChecked();
		}
	} );

	test( 'the chosen post types are checked in the general settings', async ( { page } ) => {
		await openPage( page, settingsUrl( 'easy_language_general' ) );

		// pages and posts are supported by default.
		for ( const postType of [ 'Pages', 'Posts' ] ) {
			await expect( page.getByRole( 'checkbox', { name: postType, exact: true } ) ).toBeChecked();
		}
	} );

	test( 'saving in the DataView keeps the chosen checkboxes', async ( { page } ) => {
		await openPage( page, settingsUrl( 'chatgpt' ) );
		const nonce = await getRestNonce( page );

		// save without changes (the DataView sends all settings).
		await page.getByRole( 'button', { name: 'Save', exact: true } ).click();
		await expect( page.locator( '.components-snackbar' ).first() ).toBeVisible();

		// the languages and post types are still chosen.
		const response = await page.request.get( '/wp-json/wp/v2/settings', { headers: { 'X-WP-Nonce': nonce } } );
		const settings = ( await response.json() ) as Record< string, Record< string, string > | null >;
		expect( Object.keys( settings.easy_language_chatgpt_target_languages ?? {} ) ).toEqual( expect.arrayContaining( [ 'de_LS', 'de_EL' ] ) );
		expect( Object.keys( settings.easy_language_post_types ?? {} ) ).toEqual( expect.arrayContaining( [ 'page', 'post' ] ) );
	} );

	test( 'the view of the settings could be changed', async ( { page } ) => {
		// the DataView is the default.
		await openPage( page, settingsUrl( 'advanced' ) );
		const view = page.getByLabel( 'View of the settings' );
		await expect( view ).toHaveValue( 'dataview' );

		// switch to the classic view: the page is loaded again.
		await view.selectOption( 'classic' );
		await page.getByRole( 'button', { name: 'Save', exact: true } ).click();
		await expect( page.locator( 'a.nav-tab' ).first() ).toBeVisible();
		const classicView = page.locator( 'select[name="easy_language_settings_view"]' );
		await expect( classicView ).toHaveValue( 'classic' );

		// and back to the DataView.
		await classicView.selectOption( 'dataview' );
		await page.locator( '#submit' ).click();
		await expect( page.locator( '[role="tab"]' ).first() ).toBeVisible();
		await expect( page.locator( 'a.nav-tab' ) ).toHaveCount( 0 );
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
