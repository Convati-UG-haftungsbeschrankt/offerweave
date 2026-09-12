# Varianten und Aufschläge · OfferWeave 2.13.0

Varianten sind in Pro und Owner enthalten. Free bewahrt gespeicherte Pro-Angebote, veröffentlicht Angebote mit aktivierten Varianten aber nicht als billigere vereinfachte Festpreisangebote. Die Pro-Berechnung und der Editor liegen physisch nur in den entsprechenden Ausgaben.

## Einrichten

1. **Angebote** öffnen, ein Angebot wählen und unter **Pakete und Optionen → Varianten und Aufschläge → Konfigurierbare Varianten aktivieren** einschalten.
2. Eine Optionsgruppe benennen, beispielsweise „Ausführung“. Gruppen-ID und Options-IDs sind eindeutige technische Kennungen und sollten nach Veröffentlichung stabil bleiben.
3. Optionen hinzufügen, beispielsweise „Standard“ und „Express“. Eine Standardoption wählen; für optionale Gruppen ist „Keine Auswahl“ möglich. Je Gruppe wählt der Besucher eine Option. Mehrere Gruppen können kombiniert werden.
4. Aufschlag und Berechnungsgrundlage einstellen. Beträge sind netto; Prozentwerte beziehen sich auf den berechneten Leistungspreis vor Aktionen und ohne andere Aufschläge/Reisekosten. Variantenaufschläge werden durch Angebotsaktionen nicht rabattiert.
5. **Konfiguration speichern**. Die Optionen erscheinen an Katalog- und Detailkarten sowie im Bearbeitungsbereich einer ausgewählten Position. Die Hauptsumme enthält die gewählten Aufschläge bereits.

| Grundlage                    | Berechnung                                                                             |
| ---------------------------- | -------------------------------------------------------------------------------------- |
| Je Position                  | Einmal pro Angebotsposition                                                            |
| Je Stück                     | Aufschlag × Stückzahl bei Festpreisen bzw. Mengenpaketen                               |
| Je Teilnehmer                | Aufschlag × Teilnehmerzahl                                                             |
| Je Gruppe                    | Aufschlag × berechnete Gruppenanzahl                                                   |
| Prozent des Leistungspreises | Prozentsatz auf die berechnete Grundleistung nach Gruppenstaffel, vor Angebotsaktionen |

Bei monatlicher Abrechnung gelten auch Variantenaufschläge pro Monat und fließen in die gesamte Laufzeitsumme ein. Die Auswahlliste zeigt Steuerbeträge auf den vollständigen Positionspreis. Optionsbeschriftungen folgen der gewählten Netto-/Bruttoanzeige; die detaillierte Berechnung kennzeichnet Nettobeträge ausdrücklich.

## Durchführung und Reisen

Bei Gruppenpreisen kann genau eine verpflichtende Gruppe als **Durchführung und Reisekosten** markiert werden. Für eine Option lässt sich **Die eingestellten Reisekosten für diese Option berechnen** aktivieren. Dann gelten die vorhandenen Reiseeinstellungen (offene Kosten, Eingabe von Strecke/Zeit oder inklusive). Der eigentliche Vor-Ort-Aufschlag wird als normaler Variantenaufschlag gepflegt; die alte Vor-Ort-Pauschale wird bei aktivierten Varianten nicht zusätzlich addiert.

Bei einer Paketbuchung gelten ausschließlich die Varianten des Pakets. Ein separat gebuchter Bestandteil verwendet seine eigenen Varianten und Aufschläge. Zwischen beiden besteht keine Vererbung; Varianten eines Bestandteils werden im Paket nicht zusätzlich berechnet. Bestehende Auswahlen aus der Zeit vor Varianten behalten ihre Online-/Vor-Ort-Zuordnung.

## Grenzen und Daten

Bis zu 20 Optionsgruppen mit jeweils 100 Optionen schützen vor übergroßen Konfigurationen. Diese Grenzen hängen nicht von einer höheren Lizenzstufe ab. Unbekannte/entfernte Optionen und fehlende Pflichtauswahlen werden serverseitig abgewiesen. Maximal 1.000 % prozentualer Aufschlag; keine negativen Aufschläge. Für Rabatte gibt es den bestehenden Aktionsbereich.

Beschriftungen werden über den vorhandenen Sprachbereich übersetzt; Preise und IDs bleiben gemeinsam. Gespeicherte Anfragen enthalten die damaligen Varianten, Bezeichnungen, Aufschläge und Summen. Änderungen an späteren Einstellungen schreiben frühere Anfragen nicht um.
