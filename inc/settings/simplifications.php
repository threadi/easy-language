<?php
/**
 * File to show the pro-hint for simplification-management.
 *
 * @package easy-language-pro
 */

/**
 * Add pseudo-settings-tab for this plugin.
 *
 * @return void
 */
function easy_language_add_simplifications_tab(): void {
	// output tab.
	echo '<span class="nav-tab" title="'.__('Only in Pro.', 'easy_language').'">' . esc_html__( 'Simplifications', 'easy-language' ) . ' <span class="pro-marker">Pro</span></span>';
}
add_action( 'easy_language_settings_add_tab', 'easy_language_add_simplifications_tab', 50, 0 );
