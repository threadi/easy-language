/* global MutationObserver */
/**
 * Settings of the language switcher in classic menus.
 *
 * - Shows only the fields which are relevant for the chosen display and visibility.
 * - Provides the search for pages and terms for the visibility settings.
 */
( function () {
	'use strict';

	const { __, sprintf } = wp.i18n;

	/**
	 * Show or hide the fields depending on the chosen options.
	 *
	 * @param {HTMLElement} container The settings container of one menu item.
	 */
	const updateFields = ( container ) => {
		const display = container.querySelector(
			'.easy-language-menu-display'
		);
		const visibility = container.querySelector(
			'.easy-language-menu-visibility'
		);
		if ( ! display || ! visibility ) {
			return;
		}
		const toggle = ( selector, show ) => {
			container.querySelectorAll( selector ).forEach( ( element ) => {
				element.hidden = ! show;
			} );
		};
		toggle( '.easy-language-menu-for-links', 'toggle' !== display.value );
		toggle( '.easy-language-menu-for-icons', 'icons' === display.value );
		toggle( '.easy-language-menu-for-toggle', 'toggle' === display.value );
		toggle(
			'.easy-language-menu-for-visibility',
			'all' !== visibility.value
		);
	};

	/**
	 * Write the chosen objects in the hidden field.
	 *
	 * @param {HTMLElement} picker The picker.
	 */
	const updateValue = ( picker ) => {
		const values = [];
		picker
			.querySelectorAll( '.easy-language-object-picker-selected li' )
			.forEach( ( entry ) => values.push( entry.dataset.value ) );
		picker.querySelector( '.easy-language-object-picker-value' ).value =
			values.join( ',' );
	};

	/**
	 * Add a chosen object to the list.
	 *
	 * @param {HTMLElement} picker The picker.
	 * @param {Object}      item   The object with value and label.
	 */
	const addObject = ( picker, item ) => {
		const list = picker.querySelector(
			'.easy-language-object-picker-selected'
		);
		const exists = [ ...list.querySelectorAll( 'li' ) ].some(
			( entry ) => entry.dataset.value === item.value
		);
		if ( exists ) {
			return;
		}
		const entry = document.createElement( 'li' );
		entry.dataset.value = item.value;
		const label = document.createElement( 'span' );
		label.textContent = item.label;
		const button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'button-link easy-language-object-picker-remove';
		button.setAttribute(
			'aria-label',
			sprintf(
				/* translators: %1$s will be replaced by the title of the object. */
				__( 'Remove %1$s', 'easy-language' ),
				item.label
			)
		);
		button.innerHTML =
			'<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>';
		entry.append( label, button );
		list.append( entry );
		updateValue( picker );
	};

	/**
	 * Initialize the object picker.
	 *
	 * @param {HTMLElement} picker The picker.
	 */
	const initPicker = ( picker ) => {
		if ( picker.dataset.initialized ) {
			return;
		}
		picker.dataset.initialized = '1';

		const search = picker.querySelector(
			'.easy-language-object-picker-search'
		);
		const results = picker.querySelector(
			'.easy-language-object-picker-results'
		);
		let timeout = null;

		// remove chosen objects.
		picker.addEventListener( 'click', ( event ) => {
			const remove = event.target.closest(
				'.easy-language-object-picker-remove'
			);
			if ( remove ) {
				event.preventDefault();
				remove.closest( 'li' ).remove();
				updateValue( picker );
			}
		} );

		// prevent the submit of the menu form on enter in the search field.
		search.addEventListener( 'keydown', ( event ) => {
			if ( 'Enter' === event.key ) {
				event.preventDefault();
			}
		} );

		// counter to ignore outdated search results.
		let requestCounter = 0;

		// search for objects.
		search.addEventListener( 'input', () => {
			clearTimeout( timeout );
			timeout = setTimeout( () => {
				const value = search.value.trim();
				results.innerHTML = '';
				if ( value.length < 2 ) {
					return;
				}
				const request = ++requestCounter;
				wp.apiFetch( {
					path:
						'/easy-language/v1/switcher-objects?search=' +
						encodeURIComponent( value ),
				} )
					.then( ( items ) => {
						if ( request !== requestCounter ) {
							return;
						}
						results.innerHTML = '';
						if ( ! items.length ) {
							const empty = document.createElement( 'li' );
							empty.textContent = __(
								'Nothing found.',
								'easy-language'
							);
							results.append( empty );
							return;
						}
						items.forEach( ( item ) => {
							const entry = document.createElement( 'li' );
							const button = document.createElement( 'button' );
							button.type = 'button';
							button.className = 'button-link';
							button.textContent = item.label;
							button.addEventListener( 'click', () => {
								addObject( picker, item );
								results.innerHTML = '';
								search.value = '';
								search.focus();
							} );
							entry.append( button );
							results.append( entry );
						} );
					} )
					.catch( () => {
						results.innerHTML = '';
					} );
			}, 300 );
		} );
	};

	/**
	 * Initialize all settings containers in the given element.
	 *
	 * @param {HTMLElement|Document} root The element.
	 */
	const init = ( root ) => {
		root.querySelectorAll(
			'.easy-language-menu-switcher-settings'
		).forEach( ( container ) => {
			// initialize each container only once.
			if ( container.dataset.initialized ) {
				return;
			}
			container.dataset.initialized = '1';
			updateFields( container );
			container.querySelectorAll( 'select' ).forEach( ( select ) => {
				select.addEventListener( 'change', () =>
					updateFields( container )
				);
			} );
			container
				.querySelectorAll( '.easy-language-object-picker' )
				.forEach( initPicker );

			// use the WordPress color picker for color fields.
			if ( window.jQuery && window.jQuery.fn.wpColorPicker ) {
				window
					.jQuery( container )
					.find( '.easy-language-color-field' )
					.wpColorPicker();
			}
		} );
	};

	document.addEventListener( 'DOMContentLoaded', () => {
		init( document );

		// initialize settings of newly added menu items.
		const menu = document.getElementById( 'menu-to-edit' );
		if ( menu ) {
			new MutationObserver( () => init( menu ) ).observe( menu, {
				childList: true,
			} );
		}
	} );
} )();
