# Easy Language

## About

This repository provides the features for the free version of the WordPress plugin _Easy Language_. The repository is used as a basis for deploying the plugin to the WordPress repository. It is not intended to run as a plugin as it is, even if that is possible for development.

## Usage

After checkout go through the following steps:

### Using ant

1. copy _build/build.properties.dist_ to _build/build.properties_.
2. modify the build/build.properties file - note the comments in the file.
3. execute the following command in _build/_: `ant init`
4. after that the plugin can be activated in WordPress.

### Using Taskfile

1. Run this command: `task prepare`
2. after that the plugin can be activated in WordPress.

## Release

### from local environment with ant (fallback for GitHub)

Runs the same steps as the GitHub action and creates a zip with the same content.
Not available locally: the Plugin Check (PCP), the SBOM and the GitHub release itself.

1. set the version number in _build/build.properties_.
2. optional: set _npx.path_ there to run the smoke test in WordPress Playground (needs Node 24).
3. execute the following command in _build/_: `ant build`
4. after that you will find the zip in _releases/<version>/easy-language.zip_.

### from local environment with Taskfile

Runs the same steps as the GitHub action and creates a zip with the same content (needs composer, npm, php, rsync and zip).
Not available locally: the Plugin Check (PCP), the SBOM and the GitHub release itself.

1. execute the following command in main directory: `task release -- 3.3.0` - adjust the version number.
2. optional: prefix it with `SKIP_CHECKS=true` to skip PHPStan and PHPCS, or with `SMOKE=true` to run the smoke test in WordPress Playground (needs Node 24).
3. after that you will find the zip in _releases/<version>/easy-language.zip_.

### on GitHub

1. Add a section for the new version to _changelog.md_ (e.g. `## [3.3.0] - 01.11.2026`).
2. Create a new tag with the new version number (e.g. `3.3.0`).
3. The GitHub action builds the zip, checks it (Plugin Check, smoke test in WordPress Playground, E2E tests) and attaches it together with an SBOM to the release. The release is only published if the E2E tests have run successfully.
4. A manual run of the action with a version number creates a test build without a release.

## Translations

I recommend using [PoEdit](https://poedit.net/) to translate texts for this plugin.

### generate pot-file

Run in the main directory:

`wp i18n make-pot . languages/easy-language.pot --exclude=blocks/switcher/src/,blocks/navigation-switcher/src/,svn/`

### update translation-file

1. Open .po-file of the language in PoEdit.
2. Go to "Translate" > "Update from POT-file".
3. After this the new entries are added to the language-file.

### export translation-file

1. Open .po-file of the language in PoEdit.
2. Go to "File" > "Save".
3. Upload the generated .mo-file and the .po-file to the plugin-folder languages/

### generate json-translation-files

Run in the main directory:

`wp i18n make-json languages`

### Requirements

`npm install`

### Run for development

`npm start`

### Run for release

`npm run build`

Hint: will be called by ant-command mentioned above.

## Check for WordPress Coding Standards

### Initialize

`composer install`

### Run

`vendor/bin/phpcs .`

### Repair

`vendor/bin/phpcbf .`

## Generate documentation

`vendor/bin/wp-documentor parse app --format=markdown --output=doc/hooks.md --prefix=easy_language_ --exclude=Section.php --exclude=Tab.php --exclude=Import.php --exclude=Export.php --exclude=Field_Base.php --exclude=Settings.php --exclude=Page.php --exclude=Rest.php --exclude=Transients.php --exclude=Post_Object.php`

## Check for WordPress VIP Coding Standards

Hint: this check runs against the VIP-GO-platform which is not our target for this plugin. Many warnings can be ignored.

### Run

`vendor/bin/phpcs --extensions=php --ignore=*/vendor/*,*/doc/*,*/svn/*,*/node_modules/*,*/gettext-helper.php,*/legacy-classes/Divi/build/,*/blocks/*/build/,*/js/generated/,*translatepress* --standard=WordPress-VIP-Go .`

## Check PHP compatibility

`vendor/bin/phpcs -p app --standard=PHPCompatibilityWP`

## Analyze with PHPStan

`vendor/bin/phpstan analyse`

## Check with plugin "Plugin Check"

`wp plugin check --error-severity=7 --warning-severity=6 --include-low-severity-errors --categories=plugin_repo --format=json --slug=easy-language .`

## Unit tests

### Preparation

Be sure to have run `composer install` or `composer update` before.

### Run

`composer test`

## E2E tests

The E2E tests run with [Playwright](https://playwright.dev/) against WordPress Playground, which is started by Playwright itself.
They check the settings, the simplification of pages in the backend (list of pages and block editor), the language switcher
and the simplified pages in the frontend, the blocks and the permissions.

The ChatGPT API is replaced by a mock (_tests/e2e/mu-plugins/_), which adds "Leicht gesagt: " in front of every text.
No account at OpenAI and no network access for the API is needed. The test data (API, users and pages) is created by _tests/e2e/setup.php_.

### Preparation

1. `composer install`
2. `npm ci` and `npm run build`
3. build react-dialog in _vendor/threadi/react-dialog/_ with `npm install` and `npm run build`
4. `npx playwright install chromium`

Or use `task prepare` for steps 1 to 3.

### Run

`npm run test:e2e`

Optional environment variables are described in _playwright.config.ts_, e.g. to use a local copy of WordPress instead of downloading it.
The tests also run in the GitHub action "Build release zip", the report of Playwright is attached to its run.
