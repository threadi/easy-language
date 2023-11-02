<?php

/**
 * Embed wp-easy-dialog.
 */
add_action( 'admin_enqueue_scripts', 'custom_dialog_embed', PHP_INT_MAX );
function custom_dialog_embed(): void {
	$path = __FILE__;

	// embed the dialog-component.
	$script_asset_path = $path . 'build/index.asset.php';
	$script_asset      = require( $script_asset_path );
	wp_enqueue_script(
		'wp-easy-dialog',
		$path . 'build/index.js',
		$script_asset['dependencies'],
		$script_asset['version'],
		true
	);
	$admin_css      = $path . 'build/style-index.css';
	$admin_css_path = $path . 'build/style-index.css';
	wp_enqueue_style(
		'wp-easy-dialog',
		$admin_css,
		array( 'wp-components' ),
		filemtime( $admin_css_path )
	);
}
