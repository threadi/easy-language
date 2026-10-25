<?php
/**
 * Template for the module "Language Switcher" in Themify Builder.
 *
 * Themify Builder includes this template in the context of Themify_Builder_Component_Module with the variable $args.
 * It could be overridden by copying it to your_theme/themify-builder/template-easy-language-switcher.php.
 *
 * @package easy-language
 */

// prevent direct access.
defined( 'ABSPATH' ) || exit;

// bail if the arguments are missing.
if ( ! isset( $args ) || ! is_array( $args ) ) {
	return;
}

// get the settings of the module.
$easy_language_mod_name   = (string) $args['mod_name'];
$easy_language_element_id = (string) $args['module_ID'];
$easy_language_settings   = ( is_array( $args['mod_settings'] ) ? $args['mod_settings'] : array() ) + array(
	'add_css_text'     => '',
	'animation_effect' => '',
);

// get the output of the switcher.
$easy_language_output = \easyLanguage\PageBuilder\Themify::get_instance()->render_module( $easy_language_settings );

// bail if there is no output (e.g. hidden by the visibility settings) and this is not the editor.
if ( '' === $easy_language_output && false === Themify_Builder::$frontedit_active ) {
	return;
}

// collect the classes of the module like other modules of Themify Builder.
$easy_language_classes = apply_filters( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	'themify_builder_module_classes',
	array(
		'module',
		'module-' . $easy_language_mod_name,
		$easy_language_element_id,
		$easy_language_settings['add_css_text'],
	),
	$easy_language_mod_name,
	$easy_language_element_id,
	$easy_language_settings
);
if ( ! empty( $easy_language_settings['global_styles'] ) && false === Themify_Builder::$frontedit_active ) {
	$easy_language_classes[] = $easy_language_settings['global_styles'];
}

// collect the attributes of the module container (incl. animations and sticky settings).
$easy_language_props = apply_filters( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	'themify_builder_module_container_props',
	Themify_Builder_Component_Module::parse_animation_effect(
		$easy_language_settings,
		array(
			'class' => implode( ' ', array_filter( array_map( 'strval', (array) $easy_language_classes ) ) ),
		)
	),
	$easy_language_settings,
	$easy_language_mod_name,
	$easy_language_element_id
);
Themify_Builder_Component_Module::sticky_element_props( $easy_language_props, $easy_language_settings );

// output.
?>
<div <?php echo themify_get_element_attributes( $easy_language_props ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php echo $easy_language_output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped by the switcher. ?>
</div>
