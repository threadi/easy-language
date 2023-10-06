=== Easy Language ===
Contributors: laolaweb, threadi
Tags: easy language, plain language, leichte sprache, summ ai
Requires at least: 6.0
Tested up to: 6.3
Requires PHP: 8.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Stable tag: 2.0.0

== Description ==

Have all texts on your website automatically translated into easy language. Use [SUMM AI](https://summ-ai.com)'s API to make your site even more accessible for your visitors.

=== Restrictions ===

You will be able to translate 1800 characters via SUMM AI API without any costs. If you want to translate more characters, use our Pro-version of this plugin together with an API Key from SUMM AI.

=== Features ===

* Adds easy language to other multilingual plugins like WPML, Polylang, Sublanguage, TranslatePress
* Use SUMM AI API to automatically translate texts with our plugin or TranslatePress
* PageBuilder: native support for Block Editor (Gutenberg); other page builder (like Elementor, Divi, WPBakery) are supported in Pro-plugin
* Multisite: all translated texts will be available to any other blog in the network
* Own translator role for users to only translate texts.

The development repository is on [GitHub](https://github.com/threadi/easy-language).

---

== Installation ==

1. Upload "easy-language" to the "/wp-content/plugins/" directory.
2. Activate the plugin through the "Plugins" menu in WordPress.

== Frequently Asked Questions ==

= Can I use the plugin without a SUMM AI account? =

The plugin can be installed even without SUMM AI account. You could translate all of your texts in easy language manually.

= Does this plugin has an impact to the performance in the frontend? =

No. By this plugin translated texts are handled as if they are Wordpress-native data.

= Can I use this plugin in a multisite-installation? =

Yes, you can! All translated texts will be available to any other blog in the multisite-network.

= Will I be notified if the original content of a translated page changes? =

Yes, an icon is displayed in the page or post list for this purpose. As soon as you manually adjust the content of the translated page and save it, the icon disappears again. We intentionally do not offer synchronization of new or adapted content of a page, as this is difficult to transfer with plain language.

== Screenshots ==


== Changelog ==

= 1.0.0 =
* Initial release

= 2.0.0 =
* Plugin completely revised
* Added translation for posts and pages in Einfache or Leichte Sprache, manually or automatic
* Added support for API-based translations via SUMM AI and Capito
* Added support for different source and target languages
* Added support for different PageBuilder (Block Editor, Classic Editor)
* Added multiple hooks
* Initially support for translating taxonomies
* Extended support for translations with TranslatePress
* WordPress Coding Standard 3.0 compatible
