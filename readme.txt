=== Easy Language ===
Contributors: laolaweb, threadi
Tags: easy language, leichte sprache, simplify, summ ai, capito
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.2
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
- the AI connectors of WordPress (Settings > Connectors, WordPress 7.0 or newer)

== Requirements ==

If you want to use [SUMM AI](https://summ-ai.com), [capito](https://www.capito.eu) or [ChatGpt](https://chat.openai.com) you need an API key for their respective API. Please contact their websites for more information.

== Highlights ==

With [capito](https://www.capito.eu) and [SUMM AI](https://summ-ai.com) it is possible to simplify up to 9000 characters FREE OF CHARGE. An API key is required for Capito.

== Features ==

- Simplify your contents into easy or plain language
- Auto-simplify using the API of SUMM AI, capito or OpenAI (ChatGPT)
- Auto-simplify using the AI connectors of WordPress (e.g. Anthropic, Google or OpenAI, WordPress 7.0 or newer)
- Edit your simplified contents manually
- Add a language switcher by shortcode at any position on your website
- Multisite support
- Own role for users to only simplify texts
- Abilities for AI clients via the WordPress Abilities API (WordPress 6.9 or newer, requires an MCP plugin)

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

You find some documentations [on this plugin page](https://plugins.thomaszwirner.de/en/plugin/easy-language/) and [in GitHub](https://github.com/threadi/easy-language/tree/master/doc).

The development repository is on [GitHub](https://github.com/threadi/easy-language).

Each release of this plugin will only be published if it fulfills the following conditions:

* PHPStan check for possible bugs
* Compliance with WordPress Coding Standards
* No failures during PHP Compatibility check

== External services ==

This plugin can connect to external services to simplify texts. No data is sent to any of them until you choose the API in the plugin settings and start a simplification (manually, via the automatic simplification in the background, via WP CLI or via an ability). Without an active API ("No API"), no external service is used.

= SUMM AI =

Used if "SUMM AI" is chosen as API. It simplifies texts into Leichte Sprache or Einfache Sprache.

* Sent for each simplification: the text to simplify (incl. HTML), the source and target language, formatting options, the URL of your website and a contact email address (by default the email of the administrator, depending on the settings the email of the editor who started the simplification or a custom address).
* Sent to check the quota or the API key (only with an own API key): the API key.
* With an own API key the data is sent directly to SUMM AI: https://backend.summ-ai.com
* Without an own API key (free quota of 9000 characters) the data is sent to a proxy of laOlaWeb, which forwards it to SUMM AI: https://api.laolaweb.com. A random identifier of your installation is sent as token. The free usage can be disabled in the settings of SUMM AI.
* Provider: SUMM AI GmbH, https://summ-ai.com
* Proxy for the free usage: laOlaWeb, https://laolaweb.com
* Privacy policy SUMM AI: https://summ.ai/datenschutz
* Privacy policy laOlaWeb: https://laolaweb.com/datenschutzerklaerung/

= capito =

Used if "capito" is chosen as API. It simplifies texts into the language levels A1, A2 and B1.

* Sent for each simplification: the text to simplify (incl. HTML), its language and the target language level, the API key.
* Sent to check the quota, the subscription or the API key: the API key.
* Service: https://api.capito.ai
* Terms of use: https://www.capito.eu/en/terms-and-conditions/

= ChatGPT (OpenAI) =

Used if "ChatGPT" is chosen as API.

* Sent for each simplification: the text to simplify (incl. HTML) together with the instruction for the simplification, the chosen model and the API key.
* Service: https://api.openai.com
* Terms of use: https://openai.com/policies/terms-of-use/
* Privacy policy: https://openai.com/policies/privacy-policy/

= WordPress Connectors =

Used if "WordPress Connectors" is chosen as API (WordPress 7.0 or newer). The texts and the instruction for the simplification are sent via the AI client of WordPress to the AI provider you have connected in Settings > Connectors. Its terms and privacy policy apply.

= Logging =

Requests and responses are saved in the database of your website to show them in the API log. These entries (incl. the texts and the contact email address) are deleted automatically after the number of days set for the logs (default: 50 days).

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
- Added toggle as new display for the language switcher (with adjustable colors, size and rounding)
- Added visibility settings for each language switcher (chosen pages, posts and terms)
- Added icon size for each language switcher and as default in the settings
- Added all switcher settings to the classic menu
- Added the language switcher as widget for Avada, Beaver Builder, Bold Page Builder, Breakdance, Bricks, Brizy, Divi 4, Divi 5, Enfold, Fusion, Live Composer, Nimble Builder, Oxygen 6, SiteOrigin, Themify Builder, Visual Composer, WPBakery (also Salient) and Zion Builder
- Fixed several wrong links of the language switcher on blog homepage, archives and drafts
- Added encryption of API keys, existing keys are migrated automatically
- Now requires WordPress 6.6 or newer
- Simplification requests now also check the permission for the respective object
- Reset of the plugin only affects the actual site in multisite
- Fixed API tokens in the request log
- Fixed possible damage to page builder data when saving simplified texts
- Fixed scheduling of the automatic simplification after updates
- Fixed many smaller bugs and PHP warnings
- Updated dependencies

[older changes](https://github.com/threadi/easy-language/blob/master/changelog.md)
