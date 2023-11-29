=== Easy Language ===
Contributors: laolaweb, threadi
Tags: easy language, plain language, leichte sprache, simplify, summ ai, capito
Requires at least: 6.0
Tested up to: 6.4.1
Requires PHP: 8.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Stable tag: 2.0.0

== Description ==

Have all texts on your website automatically simplified into easy language. Use a simplify-API like SUMM AI or capito to make your site even more accessible for your visitors.

#### Highlights ####

The [SUMM AI](https://summ-ai.com) API is usable without API Key to simplify 18000 characters FOR FREE.

#### Features ####

* Supported APIs to simplify texts in your website: [SUMM AI](https://summ-ai.com), [capito](https://www.capito.eu), [ChatGpt](https://chat.openai.com)
* The SUMM AI API is also usable in TranslatePress Automatic translations
* Adds easy language to other multilingual plugins like WPML, Polylang, Sublanguage, TranslatePress (and can be run alongside with them)
* PageBuilder: native support for Block Editor (Gutenberg), Avada, Elementor, Divi, WPBakery and Themify
* Multisite: all simplified texts will be available to any other blog in the network to prevent double simplification
* Own translator role for users to only simplified texts

The development repository is on [GitHub](https://github.com/threadi/easy-language).

#### Requirements

If you want to use [capito](https://www.capito.eu) or [ChatGpt](https://chat.openai.com) you need an API Key for their API. Please contact their websites for more information.

#### the Pro license includes:

* Support for more post-types
* Support for any taxonomies (e.g. category descriptions)
* Usage of custom SUMM AI API Key
* Use other source languages, e.g. english or francais
* Simplification of single texts
* Show where any simplified text is used in your website

---

== Installation ==

1. Upload "easy-language" to the "/wp-content/plugins/" directory.
2. Activate the plugin through the "Plugins" menu in WordPress.

== Frequently Asked Questions ==

= Does this plugin has an impact to the performance in the frontend? =

No. By this plugin simplified texts are handled as if they are Wordpress-native data. We do not output anything in fronted except the language switcher.

= Is this plugin GPRD-compatible? =

Yes, it is. We do not load any external files. API requests happen between your server and the API - not between them and your visitor.

= Can I use this plugin in a multisite-installation? =

Yes, you can! All simplified texts will be available to any other blog in the multisite-network.

= Will I be notified if the original content of a simplified page changes? =

Yes, an icon is displayed in the page or post list for this purpose. As soon as you manually adjust the content of the simplified page and save it, the icon disappears again. We intentionally do not offer synchronization of new or adapted content of a page, as this is difficult to transfer with plain language.

= Can I use WPML or Polylang to translate my page in english and with simplified texts of your plugin together? =

Yes, you can use Easy Language alongside all supported multilingual-plugins. You can translate your pages e.g. in english and use Easy Language to simplify the texts. Be aware to place two language-switcher in your website: the one of your multilingual-plugin and the one from Easy Language.

== Screenshots ==


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
