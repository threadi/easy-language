# Stabilitätsprüfung Easy Language 4.0.0 (Oktober 2026)

Geprüft wurden alle ~360 Dateien (PHP und JS) in 7 Bereichen. Jede Änderung hat, wo möglich, einen Regressionstest
(`tests/Unit/Stability/<Bereich>/`). Anschließend wurde der gesamte Diff von zwei unabhängigen Prüfern auf neu eingeführte
Fehler durchgesehen; deren Funde sind ebenfalls behoben (Abschnitt „Nachprüfung“).

**Testergebnis:** 489 Tests, alle grün – Single-Site und Multisite (vorher 380 Tests, Multisite wurde nie wirklich ausgeführt).

> Hinweis zum Testen von Multisite: Die WP-Testsuite liest die Umgebungsvariable `WP_MULTISITE=1`
> (nicht `WP_TESTS_MULTISITE`). Beispiel: `WP_MULTISITE=1 vendor/bin/phpunit`.

---

## Wichtigste Fehler (behoben)

| Bereich | Problem | Auswirkung |
|---|---|---|
| Themify-Parser | Texte mit Text als Schlüssel statt Index | TypeError beim Vereinfachen jeder Themify-Seite |
| Aktivierung | `Summ_Ai::install()` setzte die API hart auf SUMM AI | Reaktivierung überschrieb Capito/ChatGPT |
| Multisite-Reset | „Plugin zurücksetzen“ auf einer Unterseite löschte die netzwerkweiten Tabellen | Datenverlust aller Seiten |
| Multisite-Abfragen | Filter auf `originals_objects` ohne `blog_id` | Texte fremder Seiten in Warteschlange/Tabellen |
| Gemeinsame Texte | Status gilt pro Text, Ersetzung pro Objekt | gleicher Absatz in zwei Kopien → nur eine ersetzt |
| TranslatePress | SUMM AI und Capito: Antwort falsch gelesen, falscher Engine-Name `summ-ai` | nichts übersetzt, Kontingent trotzdem verbraucht |
| Capito-Quota | Rest 0 galt als „unbekannt“ | Sperre griff nie, Hinweis nie angezeigt |
| Dialog „+“ | Button-Array mit Lücken → JSON-Objekt | als Admin mit nicht konfigurierter API (auch No_Api) kein Dialog |
| Papierkorb | `wp_delete_post()` ohne force bei CPTs | Kopien eigener Post-Types endgültig gelöscht |
| Begriffe | Löschen eines Begriffs entfernte die Text-Verknüpfungen aller Objekte | Titel gleichnamiger Beiträge verloren |
| API-Key-Prüfung | jeder Nicht-200-Fehler (Timeout, 5xx) speicherte einen leeren Key | alter und neuer Key weg |
| Cron | lief parallel zu laufender AJAX-Vereinfachung und setzte deren Marker zurück | doppelte API-Aufrufe, Fortschritt bricht ab |
| Rewrite | Startseiten-Check mit Sprachcode statt URL-Teil; einfache Permalinks | Sprach-Startseite leitet auf `/` um; falsche Links |
| Intervall „20 Minuten“ | war 15 Minuten | |
| Log-Tabellen | netzwerkweit ohne `blog_id` | Admins sahen/löschten Logs anderer Seiten (jetzt `blog_id` + Migration in `version400`) |

## Alle Änderungen nach Bereich

**app/EasyLanguage (Objects, Db, Cli, Texts, Text, Post_Object, Term_Object)**
- Vereinfachung wird in allen verknüpften Objekten der Zielsprache ersetzt (außer Papierkorb, gesperrt, Automatik verhindert, gerade laufend).
- `oo.blog_id` in allen Join-Filtern; `COUNT(DISTINCT)` und keine doppelten Zeilen in `get_entries()`.
- MAX-Marker zählt nur offene Texte → Lauf ohne Limit endet sauber; Max-Requests-Prüfung nur gegen offene Texte.
- Kein API-Aufruf möglich (keine passende Sprachzuordnung) → Text bleibt `to_simplify` statt stillschweigend `in_use`.
- Gebündelter Lauf stoppt bei API-Fehler statt denselben Text erneut zu senden; Text bleibt nicht in `processing` hängen.
- `reset_simplifications()` setzt die Texte wieder auf `to_simplify`.
- Begriffe: Status `to_simplify` für bekannte Texte, eindeutiger Name pro Sprache (`-2`, `-3` …), Kopien beim Löschen des Originals mitlöschen.
- `Text::delete()` mit optionalem `$object_type`, zählt Verknüpfungen netzwerkweit.
- No-JS-Vereinfachung prüft Konfiguration, Quota und laufende Vereinfachung; nutzt aktive Sprachzuordnung.
- Titel roh (`get_post_field(..., 'raw')`) statt `get_the_title()` („Privat: Privat: …“).
- Beim Speichern einer Kopie: bekannte neue Texte werden verknüpft; ACF/SCF-Texte bleiben erhalten (neuer Filter `easy_language_keep_text_on_object_update`); ignorierte Texte bleiben ignoriert.
- `get_simplification_in_language()` nimmt die neueste Kopie statt „genau eine“.

**app/EasyLanguage (Init, Rewrite, Rest_Api, Abilities, Switcher) und admin/*.js**
- Cron: überspringt laufende Objekte, stellt Marker wieder her, aktive Sprachzuordnung, erstes geeignetes Objekt, merkt sich nicht vereinfachbare Texte einen Tag lang (Option `easy_language_automatic_simplification_skipped`), damit sie die Warteschlange nicht blockieren.
- Deaktivierung entfernt den Cron der automatischen Vereinfachung und Ability-Marker; Deinstallation entfernt vereinfachte Begriffe/Beiträge auch nicht mehr unterstützter Typen.
- `set_automatic_interval` plant nur bei geändertem, registriertem Intervall neu; Site-Health mit Kulanzzeit; Body-Class mit Leerzeichen; Locale-Wechsel nutzt den neuen Wert.
- JS: Debug-Icon hat jetzt einen Handler; Fehlerdialoge beim Anlegen einer Vereinfachung und beim Löschen aller Daten; Löschfortschritt wird während der Anfrage abgefragt.
- Abilities: geplante Läufe brechen mit No_Api/unkonfigurierter API ab, keine Planung bei Quota-Status ≠ ok, Ende-Trigger, korrekte Summen bei `find-objects`, `list-texts` ohne Objekt auf 1000 Texte begrenzt (`truncated`), gemeinsame Texte nur änderbar, wenn alle Objekte bearbeitbar sind.
- Switcher: Menü-Optionen-Prüfung über `$item_id`, Default-Marker nur bei Erfolg, Begriffe mit Rechteprüfung.

**app/Apis**
- SUMM AI: kein Überschreiben der API bei Aktivierung, unbegrenzte Quota statt 1.000.000, freier Zähler nur im Free-Modus, `is_configured()` false wenn Free-Anfragen deaktiviert, eigene Optionen bei Deinstallation, `check_admin_referer`.
- Capito: Rest 0 = aufgebraucht, Team ohne Auswahl → `me`, Quota-Hinweis beim Deaktivieren entfernt.
- Alle: Key nur bei 401/403 verwerfen, `WP_Error` im API-Log, leeres Token wird nicht gesendet, Request-Tabellen `longtext`, `get_log_entries()` pro Seite, Export/„Log leeren“ aus derselben Quelle und pro Seite, `fputcsv` mit `escape`.
- ChatGPT: unvollständige Antworten (`finish_reason=length`) werden verworfen. WordPress-Connectors: leere Provider-Liste nur 5 Minuten gecacht.

**app/Plugin**
- Reset auf Multisite-Unterseite löscht nur die Daten dieser Seite; netzwerkweite Deinstallation löscht weiterhin alles.
- Update: Versions-Neulesen nach Lock, Intervall-Migration plant Events neu, Setup-Abschluss-Prüfung weiterhin bei jedem Update, `version210` nur von < 2.1.0, alter Hook-Name in `version230` bleibt entfernt.
- Polylang-Sprache im Backend/Cron, Sprach-Icons (WP_Error, keine fremden Medien übernehmen), Log-Pagination, Log-Mindestalter 1 Tag, `esfw_step`, `get_sites()` inkl. archivierter Seiten, netzwerkweit aktive Plugins erkannt, Abilities-Optionen bei Deinstallation, maskierter Key bei Entschlüsselungsfehler leer.

**app/Parser, app/ThirdPartySupport, app/PageBuilder**
- Shortcode-Attribute werden demaskiert zurückgegeben (Ersetzen akzeptiert beide Formen); Guards gegen fehlende Attribute/Settings (Divi, WPBakery, Salient, Bold, Avada, Avia, Beaver Builder).
- Beaver Builder ersetzt auch im Entwurf; Elementor-Element-Cache wird geleert; ACF/SCF lesen unformatiert; WPML/TranslatePress/Breakdance/Divi 5 robuster (konfigurierte Pfade mit Fallback auf den bisherigen Pfad).
- Divi 5: Slash-Korrektur nur noch beim WooCommerce-Produkt-Duplizieren; Elementor-Pro-Tag nur wenn Pro geladen; Live Composer/Nimble-Vorschau nur mit Rechten; Block-Editor-Sidebar nur für Nutzer mit `edit_el_simplifier`; nicht existierende Klasse `Colibri` entfernt.

## Nachprüfung (Fehler, die die ersten Fixes eingeführt hätten – behoben)
- Nicht vereinfachbare Texte hätten die automatische Vereinfachung dauerhaft blockiert → Skip-Liste im Cron.
- Ersetzen in anderen Objekten hätte gesperrte/„Automatik verhindert“-Objekte überschrieben → werden übersprungen.
- Die Kopplung der Parser-Erkennung an `is_active()` hätte bei Themify-Themes, SeedProd Pro und Divi mit Extra den Undetected-Parser ausgelöst (ganzer Inhalt ersetzt) → **zurückgenommen**, Erkennung wie bisher.
- Reset einer Unterseite ließ deren Zeilen in den API-Request-Tabellen → werden jetzt gelöscht.
- Breakdance/Divi 5: per Filter ergänzte Widgets ohne Pfad nutzen wieder den bisherigen Standardpfad.
- Begriffe nicht registrierter Taxonomien werden bei der Deinstallation wirklich gelöscht.

## Bewusst offen gelassen (Designänderung nötig)
- **Status pro Text statt pro Objekt:** Die Ersetzung in allen Objekten ist ein Workaround. Sauber wäre der Status in `originals_objects.state` (Spalte existiert). Gesperrte/„Automatik verhindert“-Objekte behalten so ggf. den Originaltext. Bei Texten in sehr vielen Objekten (z. B. Footer) kostet jede Vereinfachung ein `wp_update_post` pro Objekt.
- **Gutenberg/Kubio mit verschachtelten Blöcken** (z. B. Listenpunkt mit Unterliste): wird weiterhin nicht ersetzt; sauber nur per `parse_blocks`/`serialize_blocks` wie bei Divi 5.
- **Undetected-Parser** speichert die per `the_content` gerenderte Ausgabe (Shortcodes eingefroren). Änderung würde alle klassischen Seiten als geändert markieren.
- **Brizy** kompiliertes HTML, **Avia** `wp_slash`, **Zion Builder** Objektliste im Editor: ohne Quellcode der Builder nicht verifizierbar.
- **Menü-Switcher:** Klone teilen sich die ID (Kinder nur unter dem ersten Klon).
- Parser-Erkennung bei Restdaten deaktivierter Builder (z. B. `_elementor_data` ohne Elementor) – siehe Nachprüfung, bewusst zurückgenommen.
- Bestehende maskiert gespeicherte Shortcode-Attribut-Texte werden nach dem Update einmal neu vereinfacht.

## Nacharbeiten vor dem Release
- **JS neu bauen:** `npm run build:sidebar` (`src/sidebar.js` → `js/generated/sidebar.js`) und `npm run build:divi-language-field` (`legacy-classes/Divi/src/language_field.js`). `admin/js.js` und `admin/simplifications.js` werden direkt geladen.
- **Übersetzungen:** neue Texte in `admin/simplifications.js`, `admin/js.js` und einigen PHP-Meldungen (z. B. „Simplification canceled“-Dialog bei fehlender Sprachzuordnung, Hinweis bei aufgebrauchter Quota im No-JS-Weg).
- Neuer Filter `easy_language_keep_text_on_object_update` und neue Option `easy_language_automatic_simplification_skipped` ggf. in die Doku.
