# Hooks

## easy_language_quota_percent

Filter um den Grenzwert zu verändern ab dem der Quota-Hinweis angezeigt wird.

Standard: 0.8

Beispiel:
```
add_filter( 'easy_language_quota_percent', function() {
 return 0.6;
}, 10, 0)
```

## easy_language_register_api

Hook um eigene API für Übersetzungen zu ergänzen. Siehe [Anleitung](register_new_api.md).

## easy_language_register_plugin

Hook um weiteres Sprach-Plugin zu ergänzen, welches _Leichte Sprache_ als zusätzliche Sprache erhalten soll.

## easy_language_pagebuilder

Filter um Support für einen zusätzlichen PageBuilder zu ergänzen.

## easy_language_gutenberg_blocks

Filter um die Liste der unterstützten Blöcke in Gutenberg zu beeinflussen.

## easy_language_possible_post_types

Filter um Liste der unterstützen Post-types zu beeinflussen.
