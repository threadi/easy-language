# Shortcode

Die folgenden Shortcode werden durch das Plugin bereitgestellt.

## Sprachwechsel

Aufbau: `[easy-language-switcher show_icons="yes"]`

Mögliche Attribute:

* display
  * Darstellung des Sprachwechslers: "text", "icons" oder "toggle" (Schalter).
  * Standard: leer, dann entscheidet "show_icons" zwischen Text und Icons.
* show_icons
  * Wenn der Wert "yes" ist, werden Icons anstelle von Text ausgegeben.
  * Standard: "no"
* hide_actual_language
  * Wenn der Wert "yes" ist, wird die aktuelle Sprache nicht mit angezeigt (gilt nicht für den Schalter).
  * Standard: "no"
* icon_size
  * Größe der Icons als CSS-Wert, z. B. "32px", "2em" oder "1.5rem". Eine Zahl ohne Einheit wird als Pixel-Wert verwendet.
  * Standard: leer, dann gilt die Standardgröße aus den Plugin-Einstellungen (Reiter Icons).
* toggle_language
  * Sprachcode der vereinfachten Sprache, zu der der Schalter wechselt, z. B. "de_LS".
  * Standard: leer, dann wird die erste aktive Sprache verwendet.
* toggle_disabled
  * Wenn der Wert "yes" ist, wird der Schalter auf Seiten ohne Vereinfachung ausgegraut angezeigt. Sonst wird er dort ausgeblendet.
  * Standard: "no"
* toggle_color_on, toggle_color_off, toggle_color_thumb
  * Farben des Schalters im Zustand an bzw. aus und des Schiebers, z. B. "#2271b1", "rgba(0,0,0,0.5)" oder "var(--meine-farbe)".
  * Standard: leer, dann gelten die Standardfarben.
* toggle_size
  * Höhe des Schalters als CSS-Wert, z. B. "24px" oder "2em". Die Breite wird daraus berechnet.
  * Standard: leer
* toggle_radius
  * Rundung des Schalters als CSS-Wert, z. B. "4px". Mit "0" wird der Schalter eckig.
  * Standard: leer (rund)
* visibility
  * "all" zeigt den Sprachwechsler überall, "include" nur auf den Objekten aus "show_on", "exclude" überall außer auf diesen Objekten.
  * Standard: "all"
* show_on
  * Kommagetrennte Liste von Seiten, Beiträgen und Begriffen als "post:ID" oder "term:ID", z. B. "post:12,term:5". Vereinfachungen dieser Objekte werden automatisch berücksichtigt.
  * Standard: leer
* include_children
  * Wenn der Wert "yes" ist, werden auch Unterseiten und Unterbegriffe der Objekte aus "show_on" berücksichtigt.
  * Standard: "no"
* include_assigned
  * Wenn der Wert "yes" ist, werden auch Inhalte berücksichtigt, die den Begriffen aus "show_on" zugeordnet sind.
  * Standard: "no"
* fallback
  * Verhalten auf Seiten ohne eigene Sprachfassung (Archive, Suchergebnisse, Blog-Startseite, 404): "auto" (wie vor Version 4.0.0), "home" (Startseiten verlinken) oder "hide" (ausblenden).
  * Standard: "auto"

Beispiel: einen Schalter nur im Bereich "Barrierefreiheit" (Seite mit der ID 12) und dessen Unterseiten anzeigen:

`[easy-language-switcher display="toggle" visibility="include" show_on="post:12" include_children="yes"]`
