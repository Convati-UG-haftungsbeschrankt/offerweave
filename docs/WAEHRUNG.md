# Globale Währung – ab OfferWeave 2.32.0

**OfferWeave → Einstellungen → Preise und Bedingungen → Währung → Standardwährung.** EUR und USD sind in Free verfügbar. Pro und Owner ergänzen alle weiteren unterstützten Währungen. ISO-Code und Name auswählen und speichern. EUR bleibt der Standard.

Die Einstellung gilt für den gesamten Anbieter-Katalog: Angebotspreise, feste Variantenaufschläge, Aktionsrabatte, Mindestwerte, Reisekosten, Editor und Vorschau, Angebotskarten, Detailblöcke, Anfrageübersichten und neue E-Mails. Steuer- und Rabattprozente bleiben unverändert.

Beim Wechsel bleiben die Zahlenwerte erhalten: 100 EUR werden zu 100 USD. Es findet keine Wechselkursumrechnung statt. Die Nachkommastellen passen sich an die Währung an, zum Beispiel EUR mit zwei, JPY ohne und KWD mit drei Stellen. Ein Wechsel, der einen vorhandenen Betrag runden müsste oder eine Eingabegrenze überschreitet, wird ohne Änderung abgebrochen. Passen Sie den betreffenden Betrag zuerst an.

Gespeicherte Anfragen, Exporte und daraus erzeugte Mails behalten ihre ursprüngliche Währung. Alte Anfragen ohne Währungsfeld werden weiterhin als EUR behandelt. Selbst geschriebene Beschreibungen und eigene Währungsangaben werden nicht automatisch umgeschrieben. Die Lizenzpreise von OfferWeave sind von dieser Einstellung unabhängig.

Beim Wechsel von Pro zu Free bleibt eine bereits gespeicherte Währung lesbar. Neu auswählen lassen sich in Free EUR und USD; Preise werden nicht ungefragt umetikettiert.

Eine Währung gilt global; unterschiedliche Währungen je Angebotskarte und Wechselkursdienste sind nicht enthalten.

## English

Open **OfferWeave → Settings → Prices and conditions → Currency → Default currency**, choose an ISO code and save. EUR and USD are available in Free; Pro and Owner add all other supported currencies. EUR remains the default.

Prices retain their numerical amount when changing currency, without exchange-rate conversion. Decimal places follow the selected currency. An inexact conversion or an exceeded input limit leaves the configuration unchanged. Tax and discount percentages remain unchanged.

The currency applies to all provider prices, editor previews, cards, detail blocks, new requests and emails. Saved requests and regenerated emails retain their original currency; legacy requests without a currency field remain EUR. Review manually written currency references. OfferWeave license prices are independent of this setting.

## Datenformat und Pflege

settings.currency enthält den ISO-4217-Code. Die historischen Feldnamen *_cents bleiben für kompatible Importe erhalten, speichern aber ganzzahlige kleinste Währungseinheiten: 10000 bedeutet bei EUR 100,00 EUR, bei JPY 10000 JPY, bei KWD 10,000 KWD. Bei einem vollständigen JSON-Import gehören Währung und Beträge zusammen; Importdaten werden nicht zusätzlich umgerechnet.

Neue Preisstände speichern currency auf Angebotsposition, Summenblock und Quote; neue Anfragen zusätzlich auf der obersten Snapshot-Ebene. Geldberechnung und Steuer-Rundung verwenden diese kleinsten Einheiten. Prozentfelder verwenden unabhängig davon weiterhin Basispunkte.

Die lokale Liste enthält 157 Währungscodes mit numerischen Nachkommastellen aus der [offiziellen ISO-4217-Liste von SIX](https://www.six-group.com/en/products-services/financial-information/market-reference-data/data-standards.html), XML-Veröffentlichung 01.01.2026, abgerufen am 10.09.2026. Fund-Einträge mit IsFund=true, Metalle und Einträge ohne numerische Nachkommastellen sind ausgeschlossen. Namen, Symbole und deutsche/englische Anordnung sind als lokale Metadaten hinterlegt. Zur Laufzeit gibt es dafür keine externen Anfragen.

Die bestehenden ganzzahligen Preisgrenzen bleiben erhalten: üblicherweise 100.000.000 kleinste Einheiten, beim Kilometerpreis 100.000. Die Eingabegrenze in Hauptwährungseinheiten hängt somit von den Nachkommastellen ab.
