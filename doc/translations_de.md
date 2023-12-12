# Übersetzungen

## Vorüberlegungen

* beim automatischen Übersetzen von kompletten Inhalten werden aus dem geparsten 
  post_content alle flow-text-Elemente ermittelt, übersetzt
  und im ungeparsten post_content ersetzt
  * geht nicht bei Elementor, da dessen Inhalte separat gespeichert sind
* gleiches auch beim Übersetzen von mehreren Blöcken/Widgets

## Weg 1: Redaktionell gesteuert

1. Post Type Item (Seite, Beitrag) sprachspezifisch kopieren.
2. Kopiertes Item in PageBuilder / Editor öffnen.

Dort hat man folgende Optionen:
- Gesamtes Item manuell übersetzen
- Gesamtes Item automatisch übersetzen
- Block/Widget auswählen und nur dieses automatisch übersetzen
- Mehrere Blöcke/Widgets auswählen und nur diese automatisch übersetzen

### ToDo

* Optionen hierfür in verschiedene PageBuilder einbauen
  * Klassik Editor 
  * Gutenberg
  * Elementor
* übersetzte Texte werden direkt im Item gespeichert

## Weg 2: Automatisch

* alle Post Types werden automatisiert übersetzt

### ToDo

* übersetzte Texte werden direkt im Item gespeichert
