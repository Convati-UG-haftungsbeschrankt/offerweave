# Mengen, Gruppen und Paketpreise – OfferWeave 2.13

Öffnen Sie **Angebote → gewünschtes Angebot → Preis und Menge**. Suche und Filter helfen bei längeren Katalogen. Die Berechnungsmodelle sind vorgegeben; Preise, Gruppengrößen, Staffelgrenzen und Rabatte sind einstellbar. Gruppen, Staffelpreise und Pakete gehören zu Pro und Eigennutzung.

## Eigenständige Berechnung

Jedes Angebot verwendet seine eigenen Preise, Mengen, Varianten und Steuern. Das gilt auch dann, wenn es als Bestandteil in einem Paket vorkommt. Die frühere Einstellung „Gemeinsame Berechnung“ entfällt. Bestehende Verknüpfungen werden einmalig in unabhängige Werte umgestellt; bereits gespeicherte Anfragen bleiben unverändert.

1. Ein Einzelangebot öffnen und unter **Preis und Menge** das Modell **Menge in Gruppen umrechnen** wählen.
2. Grundpreis, Gruppengröße und gegebenenfalls Rabatt für weitere Gruppen eintragen. Beispiel: 20 Einheiten je Gruppe ergeben bei einer Eingabe von 21 zwei berechnete Gruppen.
3. Unter **Pakete und Optionen** die eigenen Varianten und Reisekosten einstellen.
4. Im Bereich **Preis und Menge → Preisvorschau** den Entwurf mit verschiedenen Mengen prüfen. Die Vorschau speichert keine Anfrage und versendet keine Mail.

## Festes Paket oder zusammenstellbares Paket

Ein festes Paket berechnet seinen eigenen Paketpreis mit den Regeln des Pakets. Die Preise, Steuern und Varianten der enthaltenen Einzelangebote werden nicht zusätzlich berechnet. Die Bestandteilzuordnung dient dazu, den enthaltenen Umfang anzuzeigen.

Bei einem zusammenstellbaren Paket unter **Pakete und Optionen** die verfügbaren Bestandteile wählen und ihre **eigenen Preise innerhalb dieses Pakets** eintragen. Neue Zuordnungen beginnen bei 0 in der eingestellten Währung: den beabsichtigten Paket-Bestandteilpreis bewusst festlegen. Bei der Buchung wird die Summe der gewählten Paket-Bestandteilpreise nach der Mengen- oder Gruppenregel des Pakets berechnet. Spätere Preisänderungen am separat angebotenen Bestandteil ändern das Paket nicht – und umgekehrt.

Bestandteile dürfen eigene Festpreis-, Gruppen-, Staffel- oder Monatsregeln für ihre Einzelbuchung haben. Diese Regeln werden im Paket nicht übernommen. Verschachtelte Pakete, Selbstzuordnungen und fehlende Bestandteile bleiben unzulässig. Zusammenstellbare Pakete benötigen genügend aktive Bestandteile für ihre Mindestanzahl.

## Verständliche Einheiten auf der Karte

**Preiseinheit auf der Karte**, etwa „/ Gruppe“, ist ein Beschreibungstext und keine Rechenregel. **Einheiten benennen** unter **Preis und Menge** erlaubt eigene Singular- und Pluralformen für Eingabe und Abrechnung, zum Beispiel Person/Personen und Fotogruppe/Fotogruppen. Leere Angaben verwenden die bisherigen Standards.

Unter **Inhalt und Darstellung** die Option **Preiseinheit erklären** aktivieren. Bei leerem Erklärungstext zeigt das Plugin die tatsächliche Umrechnung automatisch. Alternativ einen eigenen Text mit `{participants}`, `{groups}`, `{group_size}` und `{quantity}` pflegen. Die Platzhalter bleiben technische Kennungen; die sichtbaren Texte können branchenneutral formuliert werden. Bei mehreren berechneten Gruppen zeigt die Karte den Gesamtumfang.

## Modelle und Mindestwerte unterscheiden

- **Festpreis:** Grundpreis mal eingegebener Anzahl.
- **Menge in Gruppen umrechnen:** Gesamtmenge durch Gruppengröße teilen und aufrunden; weitere Gruppen können rabattiert werden.
- **Staffelpreise:** Gesamtpreis anhand festgelegter Mengengrenzen, danach gegebenenfalls Aufschläge je zusätzlicher Einheit.
- **Auswahlpaket:** Eigene Preise der gewählten Paketbestandteile summieren; anschließend die eigene Mengen-/Gruppenregel anwenden.

Ein Preisminimum kann den berechneten Preis anheben. Eine **Bedingung für Anfragen** unter Einstellungen hebt den Preis nicht an, sondern entscheidet, ob die Auswahl bereits angefragt werden darf. Die Preisvorschau zeigt, ob ihre einzelne Testposition die Anfragebedingung erfüllt; weitere Positionen können das Ergebnis ändern.

## Fehler gezielt korrigieren

Eine Fehlermeldung nennt Angebot und Feld. **Zum Feld** öffnet den richtigen Editorbereich. Unpassende Zuordnungen bleiben sichtbar und können korrigiert werden. Ein Modellwechsel entfernt vorhandene Bestandteile nicht unbemerkt; lösen Sie nicht unterstützte Zuordnungen zuerst bewusst auf. Ein fehlgeschlagener Speicherversuch ändert die gespeicherte Konfiguration nicht.

Die vollständige bebilderte Anleitung liegt im Plugin unter **OfferWeave → Dokumentation** auf Deutsch und Englisch. Vor größeren Änderungen Konfiguration und Datenbank sichern.
