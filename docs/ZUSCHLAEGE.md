# Individuelle Zuschläge und Zusatzkosten

In Pro und Owner unter **OfferWeave → Zuschläge** eigene Namen, Berechnungsarten und Beträge pflegen. Unter **Angebote → Preis und Menge → Zugeordnete Zuschläge** die Definitionen zuweisen. Eine Definition kann mehrere Angebote versorgen. Änderungen gelten nach Speichern; historische Anfragen bleiben unverändert.

Berechnungen: Pauschale pro Position, pro Angebotsmenge, Teilnehmer oder Gruppe, Prozentsatz des Leistungsgrundpreises, oder zusätzliche Kundeneingabe mit frei benannter Einheit und zwei Mengendezimalstellen. Bei Eingaben ist der Betrag der Preis pro Einheit. Die Zuordnung gilt immer, nur vor Ort oder bei einer bestimmten Variantenoption. Mengenabhängige Grundlagen müssen zum Angebotsmodell passen.

Zuschläge übernehmen Währung, Steuer und Zahlungsrhythmus des Angebots. Prozentzuschläge werden vom Leistungsgrundpreis nach Mengenregeln, vor Aktionen berechnet; keine Verkettung und keine Rabattierung der Zuschläge. Pauschalen sind pro ausgewählter Angebotsposition, nicht einmal pro gesamter Anfrage. Pakete verwenden ihre eigenen Zuordnungen, ohne Zuschläge der Bestandteile zusätzlich zu übernehmen.

Vorhandene Vor-Ort-, Varianten- und Reisekosten bleiben unverändert. Es erfolgt keine automatische Migration; dieselben Kosten nicht doppelt konfigurieren. Verwendete Zuschläge zuerst von Angeboten lösen, bevor sie gelöscht werden. Deaktivierung ist zentral möglich. Free erhält gespeicherte Definitionen und Zuordnungen und veröffentlicht betroffene Pro-Angebote nicht mit unvollständigen Preisen.

## Datenformat

`config.surcharges` enthält `{id, name, enabled, basis, value, unit, default_quantity}`. `value` sind kleinste Einheiten der globalen Währung, bei `percent` Basispunkte. `default_quantity` und die öffentliche Eingabe `item.surcharge_quantities[id]` sind ganzzahlige Hundertstel-Mengen, unabhängig von den Währungsdezimalstellen. `125` bedeutet 1,25 Einheiten.

`offer.surcharges` enthält `{id, when, group_id, option_id}`. `when` ist `always`, `onsite` oder `variant`. Nur der Server liest Definitionen und Beträge; Besucher übergeben keine Preise. Quote-Snapshots enthalten `surcharge_details`, normale Berechnungszeilen und Texte. Zentrale Namen und Einheiten lassen sich im Sprachbereich übersetzen.
