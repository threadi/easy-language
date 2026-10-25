/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/packages/packages-i18n/
 */
import { __ } from '@wordpress/i18n';

/**
 * Registers a new block provided a unique name and an object defining its behavior.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * All files containing `style` keyword are bundled together. The code used
 * gets applied both to the front of your site and to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import './style.scss';

/**
 * Internal dependencies
 */
import edit from './edit';
import save from './save';
import { easyLanguageIcon } from '../../components';
import { getVariations } from '../../switcher-edit';

/**
 * Every block starts by registering a new block type definition.
 *
 * The attributes are defined in PHP: easyLanguage\EasyLanguage\Switcher::get_block_attributes().
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
registerBlockType( 'easy-language/navigation-switcher', {
	title: __( 'Language Switcher', 'easy-language' ),
	description: __(
		'Provides a Block for Language Switcher in navigation.',
		'easy-language'
	),
	icon: easyLanguageIcon,
	variations: getVariations( __( 'Language Switcher', 'easy-language' ) ),

	/**
	 * @see ./edit.js
	 */
	edit,

	/**
	 * @see ./save.js
	 */
	save,
} );
