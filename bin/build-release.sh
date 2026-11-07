#!/usr/bin/env bash
#
# Builds the release of Easy Language.
#
# Used by the GitHub Action "Build release zip", by "ant build" (build/build.xml) and by "task release"
# (Taskfile.yml). All of them run the same steps of this script, so all of them create the same zip.
#
# Usage:
#   bin/build-release.sh init                 Install the PHP packages (with dev packages, also runs Mozart) and build
#                                             the scripts: blocks, sidebar, page builder fields and react-dialog.
#   bin/build-release.sh prepare              Install the PHP packages for the release (without dev packages)
#                                             and generate the release autoloader.
#   bin/build-release.sh collect <version>    Copy the files of the release to dist/<slug> and set the version number.
#   bin/build-release.sh zip <file>           Create the zip <file> from dist/<slug>.
#   bin/build-release.sh smoke <file>         Test the zip <file> in WordPress Playground (needs Node 24 and internet).
#   bin/build-release.sh restore              Install the dev packages again and remove dist (for local builds after "prepare").
#
# Order of a release: init, (checks and tests), prepare, collect, zip, (smoke), restore.
#
# Needs: bash, composer, npm, rsync and zip (and npx for "smoke").
# The binaries could be set via the environment variables COMPOSER_BIN, NPM_BIN and NPX_BIN.

set -euo pipefail

# the name of the plugin folder in the zip.
SLUG="${RELEASE_SLUG:-easy-language}"

# always work in the root directory of the plugin.
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

DIST="dist/${SLUG}"

# the binaries; Ant sets them from build/build.properties.
# (not named "COMPOSER", as composer uses this variable for the name of the composer.json)
COMPOSER_CMD="${COMPOSER_BIN:-composer}"
NPM_CMD="${NPM_BIN:-npm}"
NPX_CMD="${NPX_BIN:-npx}"

error() {
  echo "Error: $1" >&2
  exit 1
}

init() {
  # install the PHP packages with dev packages. This also runs Mozart, which copies
  # easy-transients-for-wordpress to app/Dependencies.
  "$COMPOSER_CMD" install --no-interaction --no-progress

  # build blocks, sidebar and the fields of the page builders. "ci" uses the versions from package-lock.json.
  "$NPM_CMD" ci
  "$NPM_CMD" run build

  # build react-dialog (the package ships no lock file, so "npm ci" is not possible here).
  (cd vendor/threadi/react-dialog && "$NPM_CMD" install --no-audit --no-fund && "$NPM_CMD" run build)
}

prepare() {
  "$COMPOSER_CMD" install --no-dev --no-scripts --no-interaction --no-progress

  # its classes are shipped in app/Dependencies (copied by Mozart during "composer install").
  # Without this line the autoloader would also know them under their original names.
  rm -rf vendor/threadi/easy-transients-for-wordpress

  "$COMPOSER_CMD" dump-autoload -oa --no-dev
}

collect() {
  local version="${1:-}"
  if [[ ! "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.]+)?$ ]]; then
    error "'$version' is not a version number like 4.0.0 or 4.0.0-beta.1."
  fi

  # the built scripts must exist. Without them blocks, sidebar, page builder fields or dialogs would be missing silently.
  for FILE in blocks/switcher/build/index.js blocks/navigation-switcher/build/index.js js/generated/sidebar.js \
    legacy-classes/Divi/build/language_field.js brizy/build/index.js divi5/build/index.js \
    vendor/threadi/react-dialog/build/index.js; do
    if [ ! -f "$FILE" ]; then
      error "$FILE is missing - run \"bin/build-release.sh init\" before."
    fi
  done

  # start with an empty directory.
  rm -rf "$DIST"
  mkdir -p "$DIST"

  # only what is listed here ends up in the zip. Nothing is deleted from the plugin directory.
  # rsync stops with an error if one of these paths does not exist.
  # vendor/composer/tmp-* are leftovers of interrupted downloads of composer, *~ are backup files.
  rsync -aR --exclude='.*' --exclude='node_modules/' --exclude='/vendor/composer/tmp-*' --exclude='*~' \
    "${SLUG}.php" uninstall.php index.php readme.txt wpml-config.xml composer.json \
    admin app css gfx inc templates \
    blocks/*/block.json blocks/*/build \
    js/generated \
    legacy-classes/Divi/build legacy-classes/Divi/divi.js legacy-classes/Divi/Switcher_Module.php \
    legacy-classes/BoldBuilder legacy-classes/Breakdance legacy-classes/LiveComposer legacy-classes/Nimble \
    legacy-classes/Themify legacy-classes/VisualComposer legacy-classes/Avia legacy-classes/BeaverBuilder legacy-classes/SiteOrigin \
    brizy/build brizy/editor-icon.css divi5/build divi5/module \
    vendor/autoload.php vendor/composer \
    vendor/gettext \
    vendor/threadi/crypt-for-wordpress/src \
    vendor/threadi/easy-dialog-for-wordpress/build \
    vendor/threadi/easy-settings-for-wordpress \
    vendor/threadi/easy-setup-for-wordpress/build \
    vendor/threadi/easy-setup-for-wordpress/lib \
    vendor/threadi/react-dialog/build \
    "$DIST/"

  # the release must not be built with dev packages (their classes would be in the autoloader).
  if ! grep -q "'dev' => false" "$DIST/vendor/composer/installed.php"; then
    error "vendor/ contains dev packages - run \"bin/build-release.sh prepare\" before."
  fi

  # instructions for AI agents of the settings library: not needed on a website.
  rm -rf "$DIST/vendor/threadi/easy-settings-for-wordpress/skills"

  # no file of the plugin is this large: such a file is a leftover (e.g. a download) and must not be in the release.
  LARGE_FILES=$(find "$DIST" -type f -size +5M)
  if [ -n "$LARGE_FILES" ]; then
    error "These files are larger than 5 MB and would end up in the release:
$LARGE_FILES"
  fi

  # set the version number - in the copy only. "-i.bak" works with GNU and BSD sed (macOS).
  for FILE in "${SLUG}.php" uninstall.php readme.txt; do
    sed -i.bak "s/@@VersionNumber@@/${version}/g" "$DIST/$FILE"
    rm -f "$DIST/$FILE.bak"
  done

  # no placeholder must be left in the release.
  PLACEHOLDERS=$(grep -rl "@@VersionNumber@@" "$DIST" || true)
  if [ -n "$PLACEHOLDERS" ]; then
    error "These files still contain @@VersionNumber@@:
$PLACEHOLDERS"
  fi
}

create_zip() {
  local target="${1:-}"
  [ -n "$target" ] || error "No zip file given."
  [ -d "$DIST" ] || error "$DIST does not exist - run \"bin/build-release.sh collect <version>\" before."

  # get the absolute path of the zip, as zip runs in the dist directory.
  mkdir -p "$(dirname "$target")"
  target="$(cd "$(dirname "$target")" && pwd)/$(basename "$target")"

  rm -f "$target"
  (cd dist && zip -rqX "$target" "$SLUG")
  echo "Created $target"
}

smoke() {
  local zip_file="${1:-}"
  [ -f "$zip_file" ] || error "The zip \"$zip_file\" does not exist."

  local smoke_dir="dist/smoke"
  rm -rf "$smoke_dir"
  mkdir -p "$smoke_dir"
  unzip -q "$zip_file" -d "$smoke_dir"

  # check the zip itself first, so a wrong zip is not mistaken for a problem of Playground.
  if [ ! -f "$smoke_dir/${SLUG}/${SLUG}.php" ]; then
    ls -la "$smoke_dir"
    error "The zip does not contain ${SLUG}/${SLUG}.php - see the listing above."
  fi

  # "--no": use the version from package-lock.json, never download another one.
  "$NPX_CMD" --no -- @wp-playground/cli run-blueprint \
    --blueprint=./.github/blueprints/smoke.json \
    --mount="$ROOT/$smoke_dir/${SLUG}:/wordpress/wp-content/plugins/${SLUG}" \
    --verbosity=debug \
    --debug

  if ! grep -qs "SMOKE_OK" "$smoke_dir/${SLUG}/smoke-result.txt"; then
    error "SMOKE_OK not found in smoke-result.txt - check the output above."
  fi

  rm -rf "$smoke_dir"
  echo "Smoke test passed."
}

restore() {
  # remove the collected files: the zip is created and PHPCS would find all classes twice.
  rm -rf dist

  # get back the package removed by "prepare", as Mozart needs it during "composer install".
  # if composer does not know it as installed package, "composer install" below installs it.
  if "$COMPOSER_CMD" show threadi/easy-transients-for-wordpress > /dev/null 2>&1; then
    "$COMPOSER_CMD" reinstall threadi/easy-transients-for-wordpress --no-interaction --no-progress
  fi

  # install the dev packages again. This also runs Mozart.
  "$COMPOSER_CMD" install --no-interaction --no-progress
}

case "${1:-}" in
  init) init ;;
  prepare) prepare ;;
  collect) collect "${2:-}" ;;
  zip) create_zip "${2:-}" ;;
  smoke) smoke "${2:-}" ;;
  restore) restore ;;
  *) error "Usage: $0 init | prepare | collect <version> | zip <file> | smoke <file> | restore" ;;
esac
