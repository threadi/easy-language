# Changelog

## [4.0.0] - unreleased

### Added

- API keys are now saved encrypted (via Crypt for WordPress); existing keys are migrated automatically during the update
- Smoke test of the release zip in WordPress Playground, SBOM and Plugin Check in the release workflow
- Dependabot for GitHub Actions
- E2E tests with Playwright in WordPress Playground (settings, simplification in the list of pages and in the block editor, language switcher, simplified pages, blocks, permissions); the release is only published if they succeed
- New display "Toggle" for the language switcher (blocks, classic menu, shortcode, Elementor), optionally greyed out on pages without simplification
- Design settings for the toggle: colors (on, off, thumb), size and rounding
- Language switcher as native widget in Avada, Beaver Builder, Bold Page Builder, Breakdance, Bricks, Brizy, Divi 4, Divi 5, Enfold, Fusion, Live Composer, Nimble Builder, Oxygen 6, SiteOrigin, Themify Builder, Visual Composer, WPBakery (also Salient) and Zion Builder (with the same settings as the block)
- Filter easy_language_switcher_widget_fields to change the settings of the page builder widgets
- Visibility settings for each language switcher: show it only on (or hide it on) chosen pages, posts and terms, optionally with child pages/terms and contents assigned to the chosen terms
- Setting for the behavior on pages without own language versions (archives, search results, blog homepage, 404)
- Icon size for each language switcher and a default icon size in the plugin settings (Icons tab)
- Settings of the classic menu switcher: display, hide actual language, icon size, toggle and visibility
- Filters easy_language_switcher_settings, easy_language_switcher_entries, easy_language_switcher_toggle, easy_language_switcher_visible, easy_language_switcher_output and easy_language_switcher_objects_limit
- Compatibility mode for language switchers created before 4.0.0, enabled automatically during the update
- Support for the WordPress Abilities API (WordPress 6.9 or newer): AI clients can use Easy Language via an MCP plugin
- Read-only abilities: get-configuration, get-object-simplifications, find-objects (missing, simplified or outdated simplifications) and list-texts
- Abilities to change data: create-simplification, save-text-simplification (AI clients can save their own simplifications without using an API quota), set-text-state, set-automatic-prevention and run-simplification (via the active API in the background)
- New settings tab "Abilities" to enable or disable the abilities, to allow only read-only or all abilities and to check whether the project can use them
- Filters easy_language_abilities and easy_language_abilities_checks
- New API "WordPress Connectors" to simplify texts in Einfache and Leichte Sprache with the AI connectors of WordPress (WordPress 7.0 or newer); it could only be activated if a connector for text generation is connected
- Settings for the API "WordPress Connectors": choice of the connector and of the source and target languages
- Filters easy_language_wp_ai_instruction, easy_language_wp_ai_request_object, easy_language_wp_ai_source_languages, easy_language_wp_ai_target_languages and easy_language_wp_ai_mapping_languages
- New setting "View of the settings" in the advanced settings: modern view (DataView, default) or classic view
- Filter easy_language_keep_text_on_object_update to keep texts of other plugins (e.g. ACF fields) linked when a simplified object is saved
- Log and API log are saved per site in multisite; existing entries are assigned to the main site during the update
- More than 100 additional unit tests, also run in multisite

### Changed

- The choice of the API uses the new field "Cards" of Easy Settings: it looks the same in the classic view and in the DataView; APIs which could not be used could not be chosen
- Updated dependencies
- Now requires WordPress 6.6 or newer
- API keys are no longer readable via REST API and are shown masked in the settings
- Simplification requests now also check the permission for the respective object
- Lists of texts are now paginated
- GitHub Actions are pinned to commits and run with minimal permissions
- Removed the Ant build
- All outputs of the language switcher use one central function (Switcher::get() and Switcher::get_entries())
- The attributes of both switcher blocks are defined only once
- New blocks are wrapped in an element which uses the block settings (colors, typography, border, anchor); in navigations they are output as list item
- Active language is marked with aria-current, icons get an accessible name
- Simplified texts are replaced in all simplified objects which use them, not only in the object which is simplified (except trashed, locked objects and objects with prevented automatic mode)
- Texts which could not be simplified (e.g. language not supported by the active API) stay "to simplify" instead of being marked as simplified
- Automatic simplification skips texts which could not be simplified for one day, so they no longer block the following texts
- Automatic simplification no longer runs on objects which are simplified at the same time via the dialog
- A simplification in the dialog stops on an API error instead of sending the same text again
- A changed API key is only removed if the API rejects it (401/403), on other errors the previous key is kept
- SUMM AI: paid keys for which the API returns no character limit are no longer blocked after 1,000,000 characters
- SUMM AI: without API key and with disabled free requests the API counts as not configured
- ChatGPT: incomplete responses (cut off at the token limit) are no longer saved as simplification
- Block editor sidebar is only shown to users who are allowed to simplify
- Request logs of the APIs can save long texts

### Fixed

- Bricks (and all other page builders): widgets marked as "no HTML" were handled as HTML
- Divi 5: texts of headings, buttons and icon list items are now read from and written to the block attributes, where Divi 5 saves them
- SeedProd: simplifications without the capability "unfiltered_html" (e.g. via cron) no longer remove closing HTML tags from the page data
- Deleting a simplification permanently (without trash) now also removes its language from the original
- Requesting a term by ID no longer returns a post with the same ID
- ChatGPT: the prompt and the text to simplify are now separated by an empty line (before, the last sentence of the prompt and the first one of the text merged)
- Checkboxes with array values (e.g. supported post types, languages) were empty in the DataView and could be removed by saving there
- Progress of the simplifications in the admin bar was shown up to one minute after all texts had been simplified
- Quota with more spent characters than its limit is now handled as used up (before only a rest of exactly 0)
- Simplification via API in the backend is canceled with a hint if the quota is used up, no request is sent
- Dialog after adding a simplification no longer hangs if the quota is used up or not sufficient for all texts
- Creating a simplification outside of wp-admin (e.g. via REST API) failed because of a missing function
- API tokens were saved in the request log tables; existing entries are cleaned up during the update
- Possible SQL injection via sort order in the log
- Page builder data (Bricks, Brizy, Visual Composer, SeedProd, Beaver Builder, Breakdance, Divi 5, Themify) could be damaged when simplified texts were saved
- Automatic simplification was not scheduled after some updates due to an unknown cron interval
- Missing gettext library in the release zip broke the export of translations
- Language switcher on the blog homepage linked the versions of the first post in the list
- Language switcher was not shown on archives of supported taxonomies
- Classic menu showed the item "Language Switcher" with a useless link on pages without languages
- Simplifications which are not published (e.g. drafts) were linked in the language switcher
- Link-mode "do not link translated pages" generated relative links on pages without simplification
- Simplified terms were not linked with the default link-mode
- Active language was not detected on the language-specific homepage
- Saving menus via code (e.g. WP-CLI or importer) stopped with "The link you followed has expired"
- The icon option of the classic menu was shown on all menu items
- Many smaller bugs, missing checks and PHP warnings
- Themify: simplification of a page stopped with a fatal error
- Reactivating the plugin switched the active API to SUMM AI
- Quota of capito and SUMM AI was requested even if the API was not active or no key was set
- Multisite: texts of other sites were shown in the lists and used by the automatic simplification
- Texts used in several simplified objects (e.g. the same paragraph on two pages) were only replaced in one of them
- Moving an original of a custom post type to trash deleted its simplifications permanently
- Deleting a term removed the texts of other objects with the same text (e.g. a post with the same title)
- Simplified terms: already known texts were not simplified, a second language could not be added, simplifications were not deleted with the original
- Titles of private or protected posts were saved with the prefix "Private:" or "Protected:" and with HTML entities
- Texts added to a simplified page were not simplified if they were already known from another page
- ACF/SCF: fields were read formatted and their texts were removed when the simplified object was saved
- TranslatePress: SUMM AI and capito returned no translations, SUMM AI was not detected as translation engine
- capito: a rest quota of 0 was not handled as used up; team accounts without team used a wrong URL
- SUMM AI: the free quota was also counted in paid mode
- Dialog to add a simplification did not open for administrators if the API was not configured
- Errors while adding a simplification or deleting all data were not shown
- Language front page redirected to the homepage, simplified posts had wrong links with plain permalinks
- Changing the site language saved the previous language as source language for SUMM AI
- Polylang: source language was "en_US" in backend and cron
- Interval "every 20 minutes" ran every 15 minutes
- Page builders activated network-wide in multisite were not detected
- Elementor: dynamic tag caused a fatal error if Elementor Pro was not loaded; simplified texts were not shown with element caching
- Beaver Builder: the draft kept the original texts and overwrote the simplification on next publish
- Shortcode attributes with quotes or brackets were simplified again after every save
- Language icons: a failed import marked another post as icon; own media files with the same name could be deleted on uninstall
- Uninstall left simplified terms, simplified posts of no longer supported post types and some options
- Export of the API log contained entries which were already cleared
- Site Health reported the automatic simplification as not running if it was only a few seconds late

## [3.2.3] - 13.09.2026

### Changed

- Updated dependencies
- Changed styling of custom buttons in settings for better visibility in WordPress 7.x

### Fixed

- Fixed the wrong version number in the WordPress repository

## [3.2.2] - 09.08.2026

### Changed

- Compatible with WordPress 7.1
- Now requires PHP 8.2 or newer
- Updated dialog lib to 2.0.0
- Updated settings lib to 3.0.0
- Updated dependencies for the blocks

### Fixed

- Fixed an error on downloading simplifications as .po-file

## [3.2.1] - 20.07.2026

### Changed

- Updated dependencies
- API tokens will not be exported with the settings

### Fixed

- Fixed an error on the counter during the deletion of a simplified text
- Fixed a faulty usage of an object type during the process to ignore objects for simplifications

### Removed

- Removed the unused jquery dirty library from the main plugin

## [3.2.0] - 27.06.2026

### Added

- Added a sidebar panel in Block Editor instead of the old meta-box only for WordPress 7.0 or newer if the Block Editor is used
- Added configurable possibility to simply text in ACF and SCF fields (editor and textarea)

### Changed

- Optimized some codes to fulfill the plugin check action in GitHub
- Optimized check for capability on some actions
- Both blocks are now compatible with WordPress 7.0
- Updates settings library

### Fixed

- Fixed wrong path to build the switcher block
- Fixed missing loading of languages for single post in Divi

## [3.1.2] - 27.05.2026

### Changed

- Optimized icon dialog handling
- Renamed the dialog script handle to full compatibility with other plugins, which also use this script
- Optimized loading of simplified terms on "get_term_args"

### Fixed

- Fixed missing blocks in the release

## [3.1.1] - 01.05.2026

### Changed

- Hide the button to save the settings on icon management page
- Typos
- Updated dependencies

### Fixed

- Fixed wrong sized easy language icon in Polylang language selection field
- Fixed missing deletion of hint for multilingual plugins
- Fixed support for simplification of Divi 5 texts

## [3.1.0] - 09.03.2026

### Added

- Added support for Bricks to simplify texts from this page builder
- Added support for Divi 5 to simplify texts from this page builder

### Changed

- Set compatibility with WordPress 7.0
- Updated dependencies

### Fixed

- Fixed wrong loading of WP_Filesystem-handler sometimes

## [3.0.2] - 16.02.2026

### Changed

- Optimized the rewrite rule for detecting the homepage of a simplified language
- Updated dependencies

## [3.0.1] - 11.02.2026

### Changed

- Optimized some checks for datatypes
- More translatable texts
- Updated dependencies

### Fixed

- Fixed error during uninstallation of the plugin
- Fixed PHP-notice for undefined quota values during usage of SUMM AI API

## [3.0.0] - 05.01.2026

### Added

- Revamped the plugin for a more robust and sustainable structure
- Added a new object to handle all plugin settings
- Added support for any post-types that enabled title and editor as we need them for the simplification of texts
- Added support to simply taxonomy terms
- Added support for the PageBuilders Beaver Builder, Bold Page Builder, Brizy, Kubio, SiteOrigin, SeedProd and Visual Composer
- Added option to reset the plugin in backend settings (in preparation for Cyber Resilience Act)
- Added Taskfile as an alternative release building method for the plugin
- Added check against WordPress Plugin Checker during each release
- Added info in the footer of admin where our plugin adds options or shows settings
- Added our own intervals for automatic simplifications
- Added more than 100 PHP unit tests to check for potential errors

### Changed

- Requires PHP 8.1 or higher
- Changed handling of transients
- Optimized support for automatic simplifications via TranslatePress
- Optimized generating the hook documentation
- Logging when which update of the plugin has been installed
- Updated dependencies

### Fixed

- Fixed reading of HTTP status for external API responses

### Removed

- Removed any mentions of the pro-plugin which does not exist anymore

## [2.9.2] - 21.10.2025

### Changed

- Optimized untrash and real trash handling for objects
- Updated setup components

### Fixed

- Fixed the filter for simplified posts or pages in their tables if another API as SUMM AI is used
- Fixed mark for changed original objects (has been marked just not simplified objects)

## [2.9.1] - 28.09.2925

### Changed

- Mark the active entry in language switcher with class

### Fixed

- Fixed double slash in simplified URL for some permalink settings
- Fixed wrong hidden language in language switcher

## [2.9.0] - 23.09.2025

### Added

- Added support for teams in capito API
- Added new gpt-5 to list of available ChatGpt models

### Changed

- Optimizations for updated PHPStan rules to prevent errors

## [2.8.2] - 15.09.2025

### Changed

- Optimized the prevention to load files directly

### Fixed

- Fixed potential error during WPML initialization for the language icons

## [2.8.1] - 18.05.2025

### Changed

- Optimized detection of actual WordPress language for better support of WordPress Playground
- Updated dependencies

### Fixed

- Check if dirty script exists to prevent JS-error on loading

## [2.8.0] - 05.05.2025

### Changed

- Using PHPStan to check for potential PHP errors in plugin
- Optimized WordPress Coding Standard check
- Updates Avada support
- Multiple code optimizations to eliminate potential errors from the very beginning
- Optimized styling of API log table
- Added composer.json in plugin release to fulfill actual WordPress repository requirements
- Updated dependencies

### Fixed

- Fixed potential error on select field validation
- Fixed error in saving simplified titles of pages or posts
- Fixed error in reading inner blocks in Block Editor
- Fixed missing run of triggers after simplification has been saved
- Fixed missing Divi support if Block Editor is also active
- Fixed missing WpBakery support as Salient WpBakery get used even if it is not available

## [2.7.0] - 07.04.2025

### Added

- Added support for theme Salient WpBakery
- Added support for PageBuilder Breakdance

### Changed

- Optimized usage of file versions for each enqueued file for better performance during loading
- Optimized handling of SUMM AI free simplification requests
- Speedup detection of used PageBuilder
- Extended security checks during plugin build by using WP VIP recommendations
- Small optimisation regarding security of inputs and outputs
- Set compatibility with WordPress 6.8

### Removed

- Removed support link targeting the contact page of laOlaWeb

## [2.6.0] - 10.03.2025

### Added

- Added support for Avia Layout Architect from Enfold theme
- Added link to support forum in plugin list on our plugin
- Added shortcode to output the language switcher in frontend

### Changed

- Optimized hint which infos about simplifications are only visible in Pro-plugin
- Added hint where prices for each API can be found

## [2.5.0] - 17.02.2025

### Added

- Added option for embolden negative words during simplification via SUMM AI
- Added option to clear API log
- Added option to clear the plugin log
- Added SUMM AI HTML-mode as option, only usable with valid SUMM AI API key

### Changed

- Check for completed setup before initialising any admin options for this plugin
- Do not use test mode for SUMM AI without API key

## [2.4.2] - 10.02.2025

### Fixed

- Fixed error if SUMM AI quota is depleted and message about this should be shown
- Fixed potential error with third-party-plugins in multisite-installation

## [2.4.1] - 04.02.2025

### Changed

- WP CLI processing of simplification now uses the exact same tasks like automatic simplification via schedule

### Fixed

- Fixed automatic scheduled simplification of texts

## [2.4.0] - 29.01.2025

### Added

- Added default target language setting for each german language
- Added new settings for each SUMM AI supported language for new lines and separator
- Added some new hooks

### Changed

- New default target language for new installations in any german languages: plain language
-> reason: there are clearer guidelines for this in germany
- Using new SUMM AI API endpoints like /api/v1/translation/
- Progressbar on setup now jumps to green
- Updated Capito logo

### Fixed

- Assigned page template got lost during creating a new simplification object
- Fixed missing usage of test mode marker for SUMM AI

## [2.3.2] - 06.01.2025

### Added

- Added some more hooks

### Changed

- Optimized some plugin-own styles in wp-admin
- Small code optimizations
- No more difference between posts and pages in uRL generation

### Fixed

- Fixed potential wrong file paths
- Fixed Divi dialog for dialogs

## [2.3.1]

### Added

- Added new GitHub action to build releases

### Changed

- Moved changelog to GitHub

### Removed

- Removed language file generation from release build

### Fixed

- Fixed WPML-support as wpml-config.xml was missing in releases
- Fixed multiple typos

## [2.3.0] - 24.10.2024

### Added

- Added new hooks
- Added colored status icons in log table

### Changed

- Update Capito API to support of v2
- Updated support for ChatGpt language models
- Compatibility with WordPress 6.7
- Optimized the code on multiple positions
- Reduced usage of SQL-statements in backend
- Replace dialog- and setup-library with new one
- Made all plugin texts translatable

### Fixed

- Fixed update script
- Fixed usage of schedules
- Fixed import of icons on initial installation

## [2.2.2] - 26.09.2024

### Changed

- Hide language hint in setup page
- Updated blueprint.json
- Optimized build of release

## [2.2.1] 26.09.2024

### Changed

- Test-Release

## [2.2.0] - 29.07.2024

### Added

- Added setup after installation
- Added link to settings in some dialogs
- Added link to forum in settings

### Changed

- Optimized first SUMM AI API key check
- Optimized usage of JS-links in dialogs of Divi
- Optimized hint on supported languages
- Updates dependencies

### Fixed

- Fixed potential loading of wrong target languages for each API

## [2.1.2] - 18.07.2024

### Fixed

- Fixed order of logs
- Fixed test of SUMM AI token if plan does only contain easy language

## [2.1.1] - 13.06.2024

### Changed

- Compatibility with WordPress 6.6

### Fixed

- Fixed possible error with SUMM AI settings for languages with used API Key

## [2.1.0] - 19.04.2024

### Added

- Added rate us hint on settings page

### Changed

- WordPress Coding Standard 3.1 compatible and each release must pass this
- Log-format for requests to SUMM AI and capito changed to JSON
- Help and support pages are not linked language-dependent
- Changed minimum interval from minutely to 10-minutely to match WordPress-requirements
- Migrating interval from old to new value of 10-minutely
- Compatibility with WordPress 6.5.2
- Updated dependencies for Gutenberg-scripts

## [2.0.5] - 10.03.2024

### Changed

- Compatibility with WordPress 6.5
- Updated dependencies for Gutenberg-scripts

## [2.0.4] - 14.01.2024

### Fixed

- Fixed autoloader-generation

## [2.0.3] - 14.01.2024

### Added

- Added automatic generated hook documentation

### Changed

- Optimized same text-descriptions

### Fixed

- Fixed missing translation output
- Fixed wrong defined WP Bakery hook for HTML-widgets

## [2.0.2] - 15.12.2023

### Added

- Added more hooks

### Changed

- Optimized output of flags
- Optimized some codes

### Fixed

- Fixed missing language mappings for SUMM AI simplifications for austria and suisse
- Fixed missing flag-icons for capito languages
- Fixed missing API-value for target language on capito
- Fixed some typos

## [2.0.1] - 13.12.2023

### Removed

- Removed language files from release

### Fixed

- Fixed usage of language-columns in post-types (was only a problem if Pro-plugin is used with other languages)

## [2.0.0] - 05.10.2023

### Added

- Plugin completely revised
- Added simplification for posts and pages in Einfache or Leichte Sprache, manually or automatic
- Added support for API-based simplifications via SUMM AI and capito
- Added support for different source and target languages
- Added support for different PageBuilder (Block Editor, Classic Editor)
- Added multiple hooks

### Changed

- Extended support for simplifications with TranslatePress
- WordPress Coding Standard 3.0 compatible

## [1.0.0] - 31.05.2023

### Added

- Initial release
