# Changelog

## [Unreleased]

### Added

- Added default target language setting for each german language
- Added new settings for each SUMM AI supported language for new lines and separator
- Added some new hooks

### Optimized

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

### Optimized

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
