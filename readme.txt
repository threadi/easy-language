=== Easy Language ===
Contributors: laolaweb, threadi
Tags: easy language, leichte sprache, simplify, summ ai, capito
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Stable tag: 2.3.0

Simplify your website into easy or plain language - supported by AI.

== Description ==

Simplify your website into easy ir plain language - supported by AI.

The Plugin Easy Language adds the functionality to simplify and display your pages and posts into easy or plain language. The translation process can be manually or support by an AI-engine.

#### Automatic simplification with AI ####

To auto-translate your text, you can use the service (API) from

- [SUMM AI](https://summ-ai.com)
- [capito](https://www.capito.eu)
- [OpenAI (ChatGPT)](https://chat.openai.com)

#### Requirements####

If you want to use [SUMM AI](https://summ-ai.com), [capito](https://www.capito.eu) or [ChatGpt](https://chat.openai.com) you need an API key for their respective API. Please contact their websites for more information.

#### Highlights ####

With [capito](https://www.capito.eu) and [SUMM AI](https://summ-ai.com) it is possible to simplify up to 9000 characters FREE OF CHARGE. An API key is required for Capito.

#### Features ####

- Simplify German pages and blog posts into German easy or plain language (Supports German formal too)
- Simplify selected pages or post one by one
- Auto-simplify using the API of SUMM AI, capito or OpenAI (ChatGPT)
- Edit the pages or post manually
- Add a language switcher by shortcode at any position on your website
- Multisite support
- Own role for users to only simplify texts

#### Compatible with multilingual-plugins ####

- WPML
- Polylang
- Sublanguage
- TranslatePress

#### Compatible with major page builders ####

- Block Editor (Gutenberg)
- Avada
- Elementor
- Divi
- WPBakery
- Themify

Please Note: The plugin was tested in a broad variety of WordPress-pages, but we highly recommend to install and test the plugin in a development environment first.

The development repository is on [GitHub](https://github.com/threadi/easy-language).

#### the Pro license includes:

* Support for English (simplify to easy or plain language)
* Support for French (simplify to FALC)
* Support for more post-types, e.g. for WooCommerce
* Support for any taxonomies (e.g. category descriptions)
* Simplification of single texts
* Show where any simplified text is used in your website

More details: [laolaweb.com/en/plugins/easy-language-for-wordpress/](https://laolaweb.com/en/plugins/easy-language-for-wordpress/)

== Installation ==

1. Upload "easy-language" to the "/wp-content/plugins/" directory.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Go to the plugins settings and choose the API you want to use.
4. Start to simplify your website.

== Screenshots ==

1. Choose your API
2. Configure your SUMM AI API Key
3. Request simplification of a text

== Changelog ==

= 1.0.0 =
* Initial release

= 2.0.0 =
* Plugin completely revised
* Added simplification for posts and pages in Einfache or Leichte Sprache, manually or automatic
* Added support for API-based simplifications via SUMM AI and capito
* Added support for different source and target languages
* Added support for different PageBuilder (Block Editor, Classic Editor)
* Added multiple hooks
* Extended support for simplifications with TranslatePress
* WordPress Coding Standard 3.0 compatible

= 2.0.1 =
* Removed language files from release
* Fixed usage of language-columns in post-types (was only a problem if Pro-plugin is used with other languages)

= 2.0.2 =
* Added more hooks
* Optimized output of flags
* Optimized some codes
* Fixed missing language mappings for SUMM AI simplifications for austria and suisse
* Fixed missing flag-icons for capito languages
* Fixed missing API-value for target language on capito
* Fixed some typos

= 2.0.3 =
* Added automatic generated hook documentation
* Optimized same text-descriptions
* Fixed missing translation output
* Fixed wrong defined WP Bakery hook for HTML-widgets

= 2.0.4 =
* Fixed autoloader-generation

= 2.0.5 =
* Compatibility with WordPress 6.5
* Updated dependencies for Gutenberg-scripts

= 2.1.0 =
* Added rate us hint on settings page
* WordPress Coding Standard 3.1 compatible and each release must pass this
* Log-format for requests to SUMM AI and capito changed to JSON
* Help and support pages are not linked language-dependent
* Changed minimum interval from minutely to 10-minutely to match WordPress-requirements
* Migrating interval from old to new value of 10-minutely
* Compatibility with WordPress 6.5.2
* Updated dependencies for Gutenberg-scripts

= 2.1.1 =
* Compatibility with WordPress 6.6
* Fixed possible error with SUMM AI settings for languages with used API Key

= 2.1.2 =
* Fixed order of logs
* Fixed test of SUMM AI token if plan does only contain easy language

= 2.2.0 =
* Added setup after installation
* Added link to settings in some dialogs
* Added link to forum in settings
* Optimized first SUMM AI API key check
* Optimized usage of JS-links in dialogs of Divi
* Optimized hint on supported languages
* Updates dependencies
* Fixed potential loading of wrong target languages for each API

= 2.2.1 =
* Test-Release

= 2.2.2 =
* Hide language hint in setup page
* Updated blueprint.json
* Optimized build of release

= 2.3.0 =
* Added new hooks
* Update Capito API to support of v2
* Compatibility with WordPress 6.7
* Optimized some code
* Replace dialog- and setup-library with new one
* Fixed update script
