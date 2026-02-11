=== Easy Language ===
Contributors: laolaweb, threadi
Tags: easy language, leichte sprache, simplify, summ ai, capito
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Stable tag: @@VersionNumber@@

Simplify your website into easy or plain language - supported by AI.

== Description ==

Simplify your website into easy or plain language - supported by AI.

The Plugin Easy Language adds the functionality to simplify and display your pages and posts into easy or plain language. The translation process can be manual or support by an AI-engine.

== Automatic simplification with AI ==

To auto-translate your text, you can use the service (API) from

- [SUMM AI](https://summ-ai.com)
- [capito](https://www.capito.eu)
- [OpenAI (ChatGPT)](https://chat.openai.com)

== Requirements ==

If you want to use [SUMM AI](https://summ-ai.com), [capito](https://www.capito.eu) or [ChatGpt](https://chat.openai.com) you need an API key for their respective API. Please contact their websites for more information.

== Highlights ==

With [capito](https://www.capito.eu) and [SUMM AI](https://summ-ai.com) it is possible to simplify up to 9000 characters FREE OF CHARGE. An API key is required for Capito.

== Features ==

- Simplify your contents into easy or plain language
- Auto-simplify using the API of SUMM AI, capito or OpenAI (ChatGPT)
- Edit your simplified contents manually
- Add a language switcher by shortcode at any position on your website
- Multisite support
- Own role for users to only simplify texts

== Compatible with many page builders ==

- Avada
- Avia
- Beaver Builder
- Block Editor (Gutenberg)
- Bold Page Builder
- Breakdance Builder
- Brizy
- Classic Editor
- Divi
- Elementor
- Kubio
- Salient
- SeedProd
- SiteOrigin
- Themify
- Visual Composer
- WPBakery

== Compatible with multilingual-plugins ==

- WPML
- Polylang
- Sublanguage
- TranslatePress

== Compatible with other plugins ==

- Advanced Custom Fields (ACF)
- Custom Post Type UI (CPTUI)

The plugin has not been tested with all available plugins. It is expected to be compatible with most of them.

== Repository, documentation and reliability ==

We also provide Shortcodes as documented [here](https://github.com/threadi/easy-language/blob/master/doc/shortcodes.md).

You find some documentations [on this plugin page](https://plugins.thomaszwirner.de/en/plugin/easy-language/) and [in GitHub](https://github.com/threadi/easy-language/tree/master/docs).

The development repository is on [GitHub](https://github.com/threadi/easy-language).

Each release of this plugin will only be published if it fulfills the following conditions:

* PHPStan check for possible bugs
* Compliance with WordPress Coding Standards
* No failures during PHP Compatibility check

== Installation ==

1. Upload "easy-language" to the "/wp-content/plugins/" directory.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Go to the plugin settings and choose the API you want to use.
4. Start to simplify your website.

== Screenshots ==

1. Choose your API
2. Configure your SUMM AI API Key
3. Request simplification of a text

== Changelog ==

= @@VersionNumber@@ =
- Optimized some checks for datatypes
- More translatable texts
- Updated dependencies
- Fixed error during uninstallation of the plugin
- Fixed PHP-notice for undefined quota values during usage of SUMM AI API

### Removed

- Removed any mentions of the pro-plugin which does not exist anymore

[older changes](https://github.com/threadi/easy-language/blob/master/changelog.md)
