/**
 * Element "Language Switcher" of Easy Language for Visual Composer Website Builder.
 *
 * This file is written by hand (no build step necessary): it is a chunk for the webpack runtime of Visual Composer
 * in the same format as the bundles of its own elements and uses the modules (vc-cake, React) of Visual Composer.
 *
 * The settings of the element are generated in PHP (easyLanguage\PageBuilder\VisualComposer) from the field
 * definitions of the switcher and handed over in the variable window.VCV_EASY_LANGUAGE_SWITCHER.
 *
 * The element renders the shortcode of the switcher: the editor shows its output via the shortcode preview of
 * Visual Composer, and Visual Composer saves the shortcode in the content of the page.
 *
 * @package
 */
( window.vcvWebpackJsonp4x = window.vcvWebpackJsonp4x || [] ).push( [
	[ 'element' ],
	{
		'./easyLanguageSwitcher/index.js'( module, exports, webpackRequire ) {
			'use strict';

			let vcCake = webpackRequire( './node_modules/vc-cake/index.js' );
			let React = webpackRequire( './node_modules/react/index.js' );
			vcCake = vcCake && vcCake.default ? vcCake.default : vcCake;
			React =
				React && React.default && React.default.createElement
					? React.default
					: React;

			// get the data from PHP.
			const data = window.VCV_EASY_LANGUAGE_SWITCHER;
			if ( ! data || ! data.settings ) {
				return;
			}

			const vcvAPI = vcCake.getService( 'api' );

			/**
			 * Escape a value for an attribute of a shortcode.
			 *
			 * @param {*} value The value.
			 * @return {string} The escaped value.
			 */
			const escapeValue = function ( value ) {
				return String( value )
					.replace( /&/g, '&amp;' )
					.replace( /"/g, '&quot;' )
					.replace( /\[/g, '&#91;' )
					.replace( /\]/g, '&#93;' )
					.replace( /</g, '&lt;' )
					.replace( />/g, '&gt;' );
			};

			/**
			 * Return the shortcode for the given attributes of the element.
			 *
			 * @param {Object} atts The attributes.
			 * @return {string} The shortcode.
			 */
			const getShortcode = function ( atts ) {
				let shortcode = '[' + data.shortcode;
				Object.keys( data.fields ).forEach( function ( name ) {
					let value = atts[ name ];
					if ( 'switch' === data.fields[ name ] ) {
						value = value ? 'yes' : '';
					}
					if ( Array.isArray( value ) ) {
						value = value.join( ',' );
					}
					if (
						undefined === value ||
						null === value ||
						'' === value ||
						'object' === typeof value
					) {
						return;
					}
					shortcode += ' ' + name + '="' + escapeValue( value ) + '"';
				} );
				return shortcode + ']';
			};

			/**
			 * The component of the element.
			 */
			class EasyLanguageSwitcherElement extends vcvAPI.elementComponent {
				/**
				 * Constructor.
				 *
				 * @param {Object} props The properties.
				 */
				constructor( props ) {
					super( props );
					this.vcvhelper = React.createRef();
					this.timer = null;
				}

				/**
				 * Show the output of the shortcode after adding the element.
				 */
				componentDidMount() {
					this.updateShortcodeToHtml(
						getShortcode( this.props.atts ),
						this.vcvhelper.current
					);
				}

				/**
				 * Update the output of the shortcode if the settings changed.
				 *
				 * @param {Object} prevProps The previous properties.
				 */
				componentDidUpdate( prevProps ) {
					const shortcode = getShortcode( this.props.atts );
					if ( shortcode === getShortcode( prevProps.atts ) ) {
						return;
					}
					clearTimeout( this.timer );
					this.timer = setTimeout(
						function () {
							this.updateShortcodeToHtml(
								getShortcode( this.props.atts ),
								this.vcvhelper.current
							);
						}.bind( this ),
						500
					);
				}

				/**
				 * Stop pending updates.
				 */
				componentWillUnmount() {
					clearTimeout( this.timer );
				}

				/**
				 * Render the element.
				 *
				 * @return {Object} The element.
				 */
				render() {
					const atts = this.props.atts;
					let classes = 'vce-easy-language-switcher';
					const customProps = this.getExtraDataAttributes(
						atts.extraDataAttributes
					);
					if (
						'string' === typeof atts.customClass &&
						atts.customClass
					) {
						classes += ' ' + atts.customClass;
					}
					if ( atts.metaCustomId ) {
						customProps.id = atts.metaCustomId;
					}
					return React.createElement(
						'div',
						Object.assign(
							{ className: classes },
							this.props.editor,
							customProps
						),
						React.createElement(
							'div',
							Object.assign(
								{
									className:
										'vce-easy-language-switcher-wrapper vce',
									id: 'el-' + this.props.id,
								},
								this.applyDO( 'all' )
							),
							React.createElement( 'div', {
								className: 'vcvhelper',
								ref: this.vcvhelper,
								'data-vcvs-html': getShortcode( atts ),
							} )
						)
					);
				}
			}

			// add the element.
			vcCake.getService( 'cook' ).add(
				data.settings,
				function ( component ) {
					component.add( EasyLanguageSwitcherElement );
				},
				{
					css: false,
					editorCss: false,
				},
				''
			);
		},
	},
	[ [ './easyLanguageSwitcher/index.js' ] ],
] );
