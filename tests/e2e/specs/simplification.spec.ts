/**
 * Tests for the simplification of pages in the backend: in the list of pages and in the block editor.
 */
import { test, expect, type Page } from '@playwright/test';
import { dialog, expectNoPhpErrors, languageCell, openPage, pageRow, simplifiedMarker } from './helpers';

/**
 * The list of pages in the backend.
 */
const pagesUrl = '/wp-admin/edit.php?post_type=page';

/**
 * Return the ID of a page by its slug.
 *
 * @param page The page.
 * @param slug The slug.
 */
async function getPageId( page: Page, slug: string ): Promise< number > {
	const response = await page.request.get( `/wp-json/wp/v2/pages?slug=${ slug }&_fields=id` );
	expect( response.status() ).toBe( 200 );
	const [ object ] = ( await response.json() ) as Array< { id: number } >;
	expect( object, `page with slug "${ slug }" not found` ).toBeDefined();
	return object.id;
}

/**
 * Open the block editor for the given page and show the panel of Easy Language in its sidebar.
 *
 * @param page   The page.
 * @param postId The ID of the page to edit.
 */
async function openEasyLanguagePanel( page: Page, postId: number ) {
	await page.goto( `/wp-admin/post.php?post=${ postId }&action=edit` );
	await page.waitForFunction( () => ( window as any ).wp?.blocks?.getBlockTypes?.().length > 0 );

	// close the welcome guide and show the settings of the page in the sidebar.
	await page.evaluate( () => {
		const wp = ( window as any ).wp;
		wp.data.dispatch( 'core/preferences' )?.set( 'core/edit-post', 'welcomeGuide', false );
		wp.data.dispatch( 'core/edit-post' ).openGeneralSidebar( 'edit-post/document' );
	} );

	// open the panel, if it is closed.
	const toggle = page.getByRole( 'button', { name: 'Easy Language', exact: true } );
	await expect( toggle ).toBeVisible();
	if ( 'false' === ( await toggle.getAttribute( 'aria-expanded' ) ) ) {
		await toggle.click();
	}

	return page.locator( '.components-panel__body' ).filter( { has: toggle } );
}

test.describe( 'List of pages', () => {
	test( 'the list shows a column for each active language', async ( { page } ) => {
		await openPage( page, pagesUrl );

		await expect( page.locator( 'thead th#easy-language-de_ls' ) ).toHaveText( 'Leichte Sprache' );
		await expect( page.locator( 'thead th#easy-language-de_el' ) ).toHaveText( 'Einfache Sprache' );

		// the simplified pages are not listed, they are managed in the columns of their original.
		await expect( pageRow( page, 'E2E Original' ) ).toHaveCount( 1 );
		await expect( pageRow( page, `${ simplifiedMarker }E2E Original` ) ).toHaveCount( 0 );
		await expect( languageCell( page, 'E2E Original', 'de_LS' ).locator( 'a.dashicons-edit' ) ).toBeVisible();
		await expect( languageCell( page, 'E2E Original', 'de_EL' ).locator( 'a.dashicons-plus' ) ).toBeVisible();
	} );

	test( 'the pages can be filtered by the languages of their simplifications', async ( { page } ) => {
		await openPage( page, pagesUrl );

		// the view lists the originals which have a simplification in this language.
		await page.locator( '.subsubsub' ).getByRole( 'link', { name: 'Leichte Sprache' } ).click();
		await page.waitForLoadState( 'load' );
		await expectNoPhpErrors( page );

		await expect( pageRow( page, 'E2E Original' ) ).toHaveCount( 1 );
		await expect( pageRow( page, 'E2E Not Simplified' ) ).toHaveCount( 0 );
		await expect( pageRow( page, `${ simplifiedMarker }E2E Original` ) ).toHaveCount( 0 );
	} );

	test( 'a page can be simplified via the API', async ( { page } ) => {
		// the simplification runs via several AJAX requests.
		test.setTimeout( 180_000 );
		await openPage( page, pagesUrl );

		// choose to simplify the page in Leichte Sprache.
		await languageCell( page, 'E2E Via API', 'de_LS' ).locator( 'a.dashicons-plus' ).click();
		await expect( dialog( page ) ).toContainText( 'Add simplification for E2E Via API' );
		await dialog( page ).getByRole( 'button', { name: 'Simplify now via ChatGpt' } ).click();

		// wait until all texts are simplified.
		await expect( dialog( page ) ).toContainText( 'Simplification processed', { timeout: 120_000 } );
		await expect( dialog( page ) ).toContainText( 'Simplifications have been returned from ChatGpt.' );

		// the simplified page is shown in the frontend under the URL of its language.
		await dialog( page ).getByRole( 'button', { name: 'Show in frontend' } ).click();
		await page.waitForURL( /\/de_ls\// );
		await expectNoPhpErrors( page );
		await expect( page.locator( 'h1.wp-block-post-title' ) ).toHaveText( `${ simplifiedMarker }E2E Via API` );
		await expect( page.locator( '.wp-block-post-content' ) ).toContainText( `${ simplifiedMarker }Dieser Text wird über die Schnittstelle vereinfacht.` );

		// the list of pages now offers to edit the simplification instead of adding it.
		await openPage( page, pagesUrl );
		const cell = languageCell( page, 'E2E Via API', 'de_LS' );
		await expect( cell.locator( 'a.dashicons-edit' ) ).toBeVisible();
		await expect( cell.locator( 'a.dashicons-plus' ) ).toHaveCount( 0 );

		// the original is not changed.
		await openPage( page, '/e2e-via-api/' );
		await expect( page.locator( '.wp-block-post-content' ) ).toContainText( 'Dieser Text wird über die Schnittstelle vereinfacht.' );
		await expect( page.locator( '.wp-block-post-content' ) ).not.toContainText( simplifiedMarker );
	} );

	test( 'a simplification can be added for writing it by hand and be deleted again', async ( { page } ) => {
		// many page loads one after another.
		test.slow();
		await openPage( page, pagesUrl );

		// add the page in Einfache Sprache without simplifying it.
		await languageCell( page, 'E2E Manually', 'de_EL' ).locator( 'a.dashicons-plus' ).click();
		await dialog( page ).getByRole( 'button', { name: 'Just add Page' } ).click();
		await expect( dialog( page ) ).toContainText( 'Page ready for simplification' );
		await expect( dialog( page ) ).toContainText( 'The Page E2E Manually has been created in Einfache Sprache.' );
		await dialog( page ).getByRole( 'button', { name: 'Cancel' } ).click();

		// the copy contains the original texts, not simplified by the API.
		await openPage( page, pagesUrl );
		const cell = languageCell( page, 'E2E Manually', 'de_EL' );
		await expect( cell.locator( 'a.dashicons-edit' ) ).toBeVisible();
		const frontendUrl = await cell.locator( 'a.dashicons-admin-site-alt3' ).getAttribute( 'href' );
		expect( frontendUrl ).toContain( '/de_el/' );
		await openPage( page, frontendUrl! );
		await expect( page.locator( '.wp-block-post-content' ) ).toContainText( 'Dieser Text wird von Hand vereinfacht.' );
		await expect( page.locator( '.wp-block-post-content' ) ).not.toContainText( simplifiedMarker );

		// delete the simplification.
		await openPage( page, pagesUrl );
		await languageCell( page, 'E2E Manually', 'de_EL' ).locator( 'a.easy-language-trash' ).click();
		await expect( dialog( page ) ).toContainText( 'Do you really want to delete the simplified Page E2E Manually?' );
		await dialog( page ).getByRole( 'button', { name: 'Yes' } ).click();

		// WordPress confirms it with a notice (not via the URL: WordPress removes "trashed=1" from it while loading the page).
		await expect( page.locator( '#message' ) ).toContainText( '1 page moved to the Trash.' );
		await expectNoPhpErrors( page );

		// the page can be simplified again.
		await openPage( page, pagesUrl );
		await expect( languageCell( page, 'E2E Manually', 'de_EL' ).locator( 'a.dashicons-plus' ) ).toBeVisible();
	} );
} );

test.describe( 'Block editor', () => {
	test( 'the sidebar shows the languages of an original page', async ( { page } ) => {
		const panel = await openEasyLanguagePanel( page, await getPageId( page, 'e2e-original' ) );

		await expect( panel ).toContainText( 'You are editing this Page in the language German.' );
		await expect( panel.getByRole( 'row', { name: 'Leichte Sprache' } ) ).toBeVisible();
		await expect( panel.getByRole( 'row', { name: 'Einfache Sprache' } ) ).toBeVisible();
	} );

	test( 'the sidebar shows the language of a simplified page', async ( { page } ) => {
		const panel = await openEasyLanguagePanel( page, await getPageId( page, 'leicht-gesagt-e2e-original' ) );

		await expect( panel ).toContainText( 'You are editing this Page in the language Leichte Sprache.' );
	} );
} );
