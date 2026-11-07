/**
 * Tests for the output of simplified pages and the language switcher in the frontend for visitors.
 *
 * The page "E2E Original" is simplified in Leichte Sprache by tests/e2e/setup.php and contains the switcher as block,
 * the page "E2E Not Simplified" has no simplification and contains the switcher as shortcode.
 */
import { test, expect } from '@playwright/test';
import { openPage, simplifiedMarker, switcherLinks } from './helpers';

// visitors are not logged in.
test.use( { storageState: { cookies: [], origins: [] } } );

// the URL of the simplification of "E2E Original" (its slug is generated from the simplified title).
const simplifiedUrl = '/de_ls/leicht-gesagt-e2e-original/';

test.describe( 'Language switcher', () => {
	test( 'the switcher on the original links to its simplification', async ( { page } ) => {
		await openPage( page, '/e2e-original/' );

		const links = switcherLinks( page );
		await expect( links ).toHaveText( [ 'German', 'Leichte Sprache' ] );

		// the actual language is marked.
		const active = links.filter( { hasText: 'German' } );
		await expect( active ).toHaveClass( /easy-language-switcher-active/ );
		await expect( active ).toHaveAttribute( 'aria-current', 'page' );

		// Einfache Sprache is active, but not offered, as the page has no simplification in it.
		await expect( links.filter( { hasText: 'Einfache Sprache' } ) ).toHaveCount( 0 );
		await expect( links.filter( { hasText: 'Leichte Sprache' } ) ).toHaveAttribute( 'href', new RegExp( `${ simplifiedUrl }$` ) );
	} );

	test( 'the switcher leads to the simplified page and back', async ( { page } ) => {
		await openPage( page, '/e2e-original/' );

		// switch to Leichte Sprache.
		await switcherLinks( page ).filter( { hasText: 'Leichte Sprache' } ).click();
		await page.waitForURL( new RegExp( `${ simplifiedUrl }$` ) );
		await expect( switcherLinks( page ).filter( { hasText: 'Leichte Sprache' } ) ).toHaveAttribute( 'aria-current', 'page' );

		// and back to the original.
		await switcherLinks( page ).filter( { hasText: 'German' } ).click();
		await page.waitForURL( /\/e2e-original\/$/ );
		await expect( page.locator( 'h1.wp-block-post-title' ) ).toHaveText( 'E2E Original' );
	} );

	test( 'the switcher shortcode on a page without simplification offers only its language', async ( { page } ) => {
		await openPage( page, '/e2e-not-simplified/' );

		await expect( switcherLinks( page ) ).toHaveText( [ 'German' ] );
		await expect( switcherLinks( page ).first() ).toHaveClass( /easy-language-switcher-active/ );
	} );
} );

test.describe( 'Simplified page', () => {
	test( 'the simplified page shows the simplified texts', async ( { page } ) => {
		await openPage( page, simplifiedUrl );

		await expect( page.locator( 'h1.wp-block-post-title' ) ).toHaveText( `${ simplifiedMarker }E2E Original` );
		const content = page.locator( '.wp-block-post-content' );
		await expect( content.locator( 'h2' ) ).toHaveText( `${ simplifiedMarker }Eine Überschrift` );
		await expect( content ).toContainText( `${ simplifiedMarker }Das ist ein komplizierter Text mit vielen Fremdwörtern.` );
	} );

	test( 'the original keeps its texts', async ( { page } ) => {
		await openPage( page, '/e2e-original/' );

		const content = page.locator( '.wp-block-post-content' );
		await expect( content ).toContainText( 'Das ist ein komplizierter Text mit vielen Fremdwörtern.' );
		await expect( content ).not.toContainText( simplifiedMarker );
	} );

	test( 'simplified pages are not listed in the navigation of the website', async ( { page } ) => {
		await openPage( page, '/e2e-original/' );

		// the page list block of the theme lists all pages.
		const navigation = page.locator( '.wp-block-page-list' ).first();
		await expect( navigation ).toContainText( 'E2E Original' );
		await expect( navigation ).not.toContainText( simplifiedMarker );
	} );

	test( 'the simplified page has its own canonical URL', async ( { page } ) => {
		// search engines must not treat the simplification as duplicate of the original.
		await openPage( page, simplifiedUrl );
		await expect( page.locator( 'link[rel="canonical"]' ) ).toHaveAttribute( 'href', new RegExp( `${ simplifiedUrl }$` ) );

		await openPage( page, '/e2e-original/' );
		await expect( page.locator( 'link[rel="canonical"]' ) ).toHaveAttribute( 'href', /\/e2e-original\/$/ );
	} );
} );
