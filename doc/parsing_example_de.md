# Parsing Example

## Zielsetzung

Parsen von kontextbezogenen Texten für die API aus dem Quelltext jeder Seite.

## Beispiel 1

    <p>Das ist ein Beispiel.</p>

Ein sehr einfaches Beispiel. Ein einziger Satz ohne Bezug zu anderen. Wird 1:1 so für die API verwendet.

Die Rückgabe der API sähe beispielsweise so aus:

    <p>Das ist ein Beispiel.</p>

## Beispiel 2

    <p>Ich bin ein einfacher Beispieltext über einen Bäcker. Dieser Bäcker backt seine Brötchen jeden Morgen frisch.</p>
    <p>Aber wie kommen die Brötchen in den Ofen?</p>
    <p>Er schiebt alle Brötchen auf eine große Schaufel und hievt sie in den Ofen. Starke Leistung.</p>

Hier handelt es sich um mehrere Absätze zu einem Thema. Die Absätze werden im Original-Quellcode mit Zeilenumbrüchen ausgegeben.

An die API sollte dieser Quellcode so wie er ist, aber ohne die Zeilenumbrüche übergeben werden. Dadurch bleibt der Context erhalten und die API hat keine Probleme beim Erkennen von diesem. Also:

    <p>Ich bin ein einfacher Beispieltext über einen Bäcker. Dieser Bäcker backt seine Brötchen jeden Morgen frisch.</p><p>Aber wie kommen die Brötchen in den Ofen?</p><p>Er schiebt alle Brötchen auf eine große Schaufel und hievt sie in den Ofen. Starke Leistung.</p>

Die Rückgabe der API sähe beispielsweise so aus:

    <p>Ich bin ein einfacher Beispieltext. 
    Er ist ein Bäcker. 
    Er backt Brötchen.
    Die Brötchen sind jeden morgen neu</p>
    <p>Wie kommen die Brötchen in den Ofen?</p>
    <p>Der Bäcker schiebt sie in den Ofen.
    Er benutzt dazu eine Schaufel.
    Diese ist sehr groß.
    Das ist eine gute Leistung.</p>

## Beispiel 3

O.g. Text wird noch ergänzt um:

    <h2 class="wp-block-heading">Die Abrechnung</h2>
    <p>Jedes hergestellte Brötchen muss auch abgerechnet werden. Der Bäcker erfasst dazu wie viel Mehl er pro Brötchen benötigt und rechnet das zzgl. seiner eigenen Arbeitsleistung auf die Kosten für den Kunden um.</p>
    <p>Der Kunde erhält zusammen mit seinem Brötchen einen Kassenzettel. Auf diesem steht der Preis, den der Becker ihm pro Brötchen berechnet hat.</p>

Somit enthält der Text nun 2 Unternehmen zum Thema Bäcker. Der Parser erkennt das anhand der Überschrift im Text. Er trennt den Text daher an dieser Stelle auf und übergibt 2 Teile an die API zum Übersetzen:

### Teil 1

    <p>Ich bin ein einfacher Beispieltext über einen Bäcker. Dieser Bäcker backt seine Brötchen jeden Morgen frisch.</p><p>Aber wie kommen die Brötchen in den Ofen?</p><p>Er schiebt alle Brötchen auf eine große Schaufel und hievt sie in den Ofen. Starke Leistung.</p>

### Teil 2

    <h2 class="wp-block-heading">Die Abrechnung</h2><p>Jedes hergestellte Brötchen muss auch abgerechnet werden. Der Bäcker erfasst dazu wie viel Mehl er pro Brötchen benötigt und rechnet das zzgl. seiner eigenen Arbeitsleistung auf die Kosten für den Kunden um.</p><p>Der Kunde erhält zusammen mit seinem Brötchen einen Kassenzettel. Auf diesem steht der Preis, den der Becker ihm pro Brötchen berechnet hat.</p>
