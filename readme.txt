=== Easy Language ===
Contributors: laolaweb, threadi
Tags: easy language, plain language, leichte sprache, falc
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Stable tag: 1.0.0

== Description ==

Add easy language as additional language to the multilingual-plugin you are using. We actually support WPML, Polylang and TranslatePress.

This plugin also optimizes the HTML-output in frontend if an easy language is used.

The development repository is on [GitHub](https://github.com/threadi/easy-language).

== Steps for Polylang ==

After install and activation of Easy Language go to Languages > Languages. You will find there in "Choose a language" 3 new entries at the end of the list. Add one of them to use it.

== Steps for TranslatePress ==

After install and activation of Easy Language go to Settings > TranslatePress > General. You will find there in the language-select-field 3 new entries at the end of the list. Add one of them to use it.

== Steps for WPML ==

After install and activation of Easy Language you will find 3 new languages under WPML > Languages. Click on "Add languages" and enable the Easy Language you want to use.

== Frequently Asked Questions ==

= Does this plugin support automatic translate my texts in easy language? =

Actually no. But this feature will be implemented in future versions. Feel free to contact us if you have any questions.

= Why this plugin adds 3 Easy Languages? =

The plugin supports the following 3 simple languages by itself:

* Leichte Sprache in german
* Easy Language in englisch
* FALC for french

They are all included during plugin activation and can then be used or disabled depending on the plugin.

= How does this plugin change the HTML-output in frontend? =

We set the language abbreviation in the HTML code to the corresponding main language. This allows screen readers to read out the plain language in the visitor's language. Unfortunately, this is necessary because there is no standardized ISO abbreviation for plain language that is also recognized by screen readers.

---

== Installation ==

1. Upload "easy-language" to the "/wp-content/plugins/" directory.
2. Activate the plugin through the "Plugins" menu in WordPress.

== Screenshots ==

== Changelog ==

= 1.0.0 =
* Initial release