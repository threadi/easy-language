# Easy Language

## About

This repository provides the features of the free version of the WordPress plugin _Easy Language_. The repository is used as a basis for deploying the plugin to the WordPress repository. It is not intended to run as a plugin as it is, even if that is possible for development. 

## Release

1. increase the version number in _build/build.properties_.
2. execute the following command in _build/_: `ant build`
3. after that you will finde in the release directory a zip file which could be used in WordPress to install it.

## Translations

I recommend to use [PoEdit](https://poedit.net/) to translate texts for this plugin.

### generate pot-file

Run in main directory:

`wp i18n make-pot . languages/easy-language.pot --exclude=svn/`

### update translation-file

1. Open .po-file of the language in PoEdit.
2. Go to "Translate" > "Update from POT-file".
3. After this the new entries are added to the language-file.

### export translation-file

1. Open .po-file of the language in PoEdit.
2. Go to "File" > "Save".
3. Upload the generated .mo-file and the .po-file to the plugin-folder languages/

### validate file

`vendor/bin/phpcs --standard=WordPress .`