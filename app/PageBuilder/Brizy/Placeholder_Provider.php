<?php
/**
 * File for the Brizy placeholder provider of Easy Language.
 *
 * @package easy-language
 */

namespace easyLanguage\PageBuilder\Brizy;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use Brizy_Content_Placeholders_Simple;
use Brizy_Content_Providers_AbstractProvider;
use BrizyPlaceholders\ContentPlaceholder;
use easyLanguage\PageBuilder\Brizy;

/**
 * Object to provide the placeholder "{{easy_language_switcher ...}}" for Brizy.
 *
 * The placeholder is used by our language switcher element. Brizy replaces it in frontend and in the preview of
 * its editor.
 */
class Placeholder_Provider extends Brizy_Content_Providers_AbstractProvider {
	/**
	 * Constructor for this object.
	 */
	public function __construct() {
		$this->registerPlaceholderName( Brizy::PLACEHOLDER, array( $this, 'get_placeholder' ) );
	}

	/**
	 * Return the placeholder object.
	 *
	 * @param string $name The name of the placeholder.
	 *
	 * @return Brizy_Content_Placeholders_Simple
	 */
	public function get_placeholder( string $name ): Brizy_Content_Placeholders_Simple {
		return new Brizy_Content_Placeholders_Simple(
			__( 'Language Switcher', 'easy-language' ),
			$name,
			function ( $context, $content_placeholder ) {
				// bail if the placeholder is not valid.
				if ( ! $content_placeholder instanceof ContentPlaceholder ) {
					return '';
				}

				// get the attributes.
				$attributes = $content_placeholder->getAttributes();

				// return the output.
				return Brizy::get_instance()->render_placeholder( is_array( $attributes ) ? $attributes : array() );
			}
		);
	}
}
