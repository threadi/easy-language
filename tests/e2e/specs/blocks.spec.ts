/**
 * Tests for the blocks of the plugin in the block editor.
 */
import { test, expect, type Page } from '@playwright/test';

/**
 * The blocks of the plugin.
 */
const blocks = [ 'easy-language/switcher', 'easy-language/navigation-switcher' ];

/**
 * Open the block editor for a new page and wait until it is ready.
 *
 * @param page The page.
 */
async function openEditor( page: Page ): Promise< void > {
	await page.goto( '/wp-admin/post-new.php?post_type=page' );
	await page.waitForFunction( () => ( window as any ).wp?.blocks?.getBlockTypes?.().length > 0 );

	// close the welcome guide, if it is shown.
	await page.evaluate( () => {
		const wp = ( window as any ).wp;
		wp.data.dispatch( 'core/preferences' )?.set( 'core/edit-post', 'welcomeGuide', false );
	} );
}

/**
 * Return the editor canvas (the editor uses an iframe since WordPress 6.3).
 *
 * @param page The page.
 */
function canvas( page: Page ) {
	return page.frameLocator( 'iframe[name="editor-canvas"]' );
}

test.describe( 'Blocks', () => {
	test( 'all blocks of the plugin are registered', async ( { page } ) => {
		await openEditor( page );

		const names = await page.evaluate( () =>
			( window as any ).wp.blocks.getBlockTypes().map( ( type: { name: string } ) => type.name ).filter( ( name: string ) => name.startsWith( 'easy-language/' ) )
		);
		expect( names ).toEqual( expect.arrayContaining( blocks ) );
	} );

	test( 'the switcher block shows the languages in the editor', async ( { page } ) => {
		test.setTimeout( 240_000 );
		await openEditor( page );

		// the preview is rendered by the server.
		const rendered = page.waitForResponse( ( response ) => /block-renderer(\/|%2F)easy-language(\/|%2F)switcher/.test( response.url() ), { timeout: 120_000 } );
		await page.evaluate( () => {
			const wp = ( window as any ).wp;
			wp.data.dispatch( 'core/block-editor' ).insertBlocks( wp.blocks.createBlock( 'easy-language/switcher' ) );
		} );
		const response = await rendered;
		expect( response.status(), `preview request failed: ${ await response.text() }` ).toBe( 200 );

		await expect( canvas( page ).locator( '[data-type="easy-language/switcher"]' ).getByText( 'German' ) ).toBeVisible( { timeout: 60_000 } );
	} );

	test( 'the previews in the block inserter are not empty', async ( { page } ) => {
		await openEditor( page );

		// render each block like the preview in the inserter does, with the example attributes from block.json.
		const results = await page.evaluate( async ( names ) => {
			const wp = ( window as any ).wp;
			const output: Record< string, string > = {};
			for ( const name of names ) {
				const example = wp.blocks.getBlockType( name )?.example?.attributes ?? {};
				const response = await wp.apiFetch( {
					path: `/wp/v2/block-renderer/${ name }?context=edit`,
					method: 'POST',
					data: { attributes: example },
				} );
				output[ name ] = String( response.rendered ?? '' );
			}
			return output;
		}, blocks );

		for ( const [ block, html ] of Object.entries( results ) ) {
			expect( html.trim(), `preview of "${ block }" is empty` ).not.toBe( '' );
		}
	} );
} );
