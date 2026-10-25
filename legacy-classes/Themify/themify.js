/**
 * Settings of the module "Language Switcher" in Themify Builder.
 *
 * Themify Builder defines the settings of modules in JavaScript classes named "Module" + the camel-cased slug.
 * The settings itself are generated in PHP from the field definitions of the switcher
 * (see easyLanguage\PageBuilder\Themify::get_module_options()) and handed over as easyLanguageThemifyModule.
 *
 * The module has no own "preview" method, so Themify Builder renders the preview via AJAX with the PHP template.
 *
 * @param {Object} api The app object of Themify Builder (tb_app).
 * @package
 */
( ( api ) => {
	'use strict';

	// bail if Themify Builder is not loaded.
	if ( undefined === api || undefined === api.Module ) {
		return;
	}

	// get the settings from PHP.
	const data = window.easyLanguageThemifyModule || {
		options: [],
		defaults: {},
	};

	/**
	 * Return a copy of an object as Themify Builder changes the options during rendering.
	 *
	 * @param {Object} obj The object.
	 * @return {Object} The copy.
	 */
	const clone = ( obj ) => JSON.parse( JSON.stringify( obj ) );

	/**
	 * Add the symbol for the icon of our module (the Easy Language logo) to the SVG sprites of Themify Builder.
	 *
	 * Themify Builder shows module icons as "<use href="#tf-ti-<icon>">". The symbols are in the sprite "#tf_svg"
	 * of the page, which Themify Builder moves into the editor window and copies into the shadow roots of its
	 * panels. So we add our symbol to every sprite that exists.
	 *
	 */
	const addIcon = () => {
		const icon = data.icon || {};
		if ( ! icon.id ) {
			return;
		}

		// collect the documents: this one and the editor window (if it is another one with the same origin).
		const roots = [ document ];
		try {
			if ( window.top !== window && window.top.document ) {
				roots.push( window.top.document );
			}
		} catch {
			// the top window is not accessible.
		}

		// add the shadow roots of the panels of Themify Builder.
		roots.slice().forEach( ( root ) => {
			root.querySelectorAll( '[id$="_root"]' ).forEach( ( el ) => {
				if ( el.shadowRoot ) {
					roots.push( el.shadowRoot );
				}
			} );
		} );

		// add the symbol to each sprite.
		roots.forEach( ( root ) => {
			const defs = root.querySelector( '#tf_svg defs' );
			if ( ! defs ) {
				return;
			}
			if ( defs.querySelector( '[id="' + icon.id + '"]' ) ) {
				return;
			}
			const symbol = defs.ownerDocument.createElementNS(
				'http://www.w3.org/2000/svg',
				'symbol'
			);
			symbol.id = icon.id;
			symbol.setAttribute( 'viewBox', icon.viewBox );
			symbol.innerHTML = icon.content;
			defs.appendChild( symbol );
		} );
	};
	addIcon();
	document.addEventListener( 'DOMContentLoaded', addIcon );
	window.addEventListener( 'load', addIcon );

	/**
	 * The module "Language Switcher".
	 */
	api.ModuleEasyLanguageSwitcher = class extends api.Module {
		/**
		 * Return the settings of the module.
		 *
		 * @return {Array} List of settings.
		 */
		static getOptions() {
			return clone( data.options );
		}

		/**
		 * Return the default values of the module.
		 *
		 * @return {Object} The default values.
		 */
		static default() {
			return clone( data.defaults );
		}
	};
} )( window.tb_app );
