/* @jsxRuntime classic */
/**
 * Icon of the language switcher module in Divi 5: the Easy Language logo (gfx/easy-language-icon.svg).
 *
 * The URL of the logo is passed from PHP via the global object "EasyLanguageDivi5SwitcherData".
 */

// External dependencies (globals of the Divi visual builder, see webpack.config.js).
/* eslint-disable import/no-extraneous-dependencies */
import React from 'react';

export const switcherIcon = {
	name: 'easy-language/divi-switcher',
	viewBox: '0 0 16 16',
	component: () => (
		// the module picker renders icons with a viewBox of 16 × 16.
		<image
			href={ window.EasyLanguageDivi5SwitcherData?.icon ?? '' }
			x="0"
			y="0"
			width="16"
			height="16"
		/>
	),
};
