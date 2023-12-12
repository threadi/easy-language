# Umfang des Supports für ChatGpt

## Was geht

* Eingabe eines API Keys zur Authentifizierung ggü. der API
* Die Wahl eines Language Models ist möglich: gpt-4 oder gpt-3.5 (letzteres ist nur bis Januar 2024 noch verfügbar)
* Auswahl von Ausgangssprachen: nur deutsch
* Auswahl von Zielsprachen: Einfache und Leichte Sprache
* Übersetzungsmodus: deaktiviert, automatisch oder manuell
* Das Interval für automatische Übersetzungen ist wählbar
* Abhängig von der gewählten Zielsprache wird der KI bei jedem Request eine entsprechende Aufforderung zur Vereinfachung mitgeteilt
* Jeder Request und die daraus resultierende Antwort werden protokolliert. Das Protokoll wird für 50 Tage aufbewahrt (Einstellbar in globalen Einstellungen)
* Mögliche Fehler bei Anfragen sind im API-Log zu finden

## Was geht nicht

* keine anderen Sprachen außer deutsch
* kein Abruf von verfügbaren Token des ChatGpt-Kontos (wird seitens ChatGpt nicht unterstützt)
* keine Zählung verwendeter Token
