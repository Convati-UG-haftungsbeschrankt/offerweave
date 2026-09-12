# Mehrwertsteuer und Preisanzeige – ab OfferWeave 2.1.0

Die gesamte Steuerfunktion ist in **Free, Pro und der Eigennutzungs-Ausgabe** enthalten. Sie berechnet die vom Betreiber eingestellten Sätze. Sie ermittelt keine länder- oder kundenspezifische Steuerpflicht und erstellt keine Rechnung.

Die Standardwährung stellen Sie seit 2.32.0 im selben Bereich unter **Währung** ein. Sie gilt für alle Anbieterpreise und neuen Anfragen; bestehende Anfragen behalten ihre damalige Währung. Keine Wechselkursumrechnung. Die folgenden Beispiele verwenden EUR. [Währung einrichten](WAEHRUNG.md).

## Einrichtung

1. **OfferWeave → Einstellungen → Preise und Bedingungen → Mehrwertsteuer und Preisanzeige** öffnen.
2. **Mehrwertsteuer berechnen und ausweisen** einschalten. Beim Update ist die Funktion zunächst aus. Der vorgeschlagene Standardsatz von 19 % wird erst nach Aktivierung angewendet.
3. Den passenden **Standard-Mehrwertsteuersatz** eingeben. Zulässig: 0–100 % mit bis zu zwei Nachkommastellen.
4. **Netto, zzgl. MwSt.** oder **Brutto, inkl. MwSt.** auswählen und speichern. Diese Einstellung bestimmt die hervorgehobenen Preise auf Karten, Detailseiten, in der Auswahl und in neuen Mails. Die Auswahl und Mails zeigen zusätzlich Nettowerte, Mehrwertsteuer und Bruttowerte.
5. Bei Bedarf unter **Angebote → Angebot bearbeiten → Mehrwertsteuersatz für dieses Angebot** einen abweichenden Satz eintragen. Ein leeres Feld übernimmt den Standard. Eingetragene `0` bedeutet ausdrücklich 0 %.
6. Eigene Angebotsbeschreibungen, die Einleitung, übersetzte Inhalte und eigene E-Mail-Vorlagen kontrollieren: Einen selbst geschriebenen Hinweis „alle Preise netto“ bei Bruttoanzeige anpassen. Eigene Texte werden nicht überschrieben.

**Alle Preise und festen Geldrabatte im Editor weiterhin netto eingeben.** Die Anzeigeoption interpretiert vorhandene Preise nicht neu. Beispiel: `100,00 €` als Angebotspreis und `19 %` Steuer ergeben `119,00 €` bei Bruttoanzeige. Wer `119,00 €` als Nettopreis einträgt, erhält dagegen `141,61 €` brutto.

Die Darstellungswahl ist eine Einstellung des Betreibers. Besucher verändern damit weder Steuerregeln noch Preise. Es gibt keinen zusätzlichen Umschalter für den Besucher.

## Beispiel einer Auswahl

| Position                | Nettowert |               Satz | Mehrwertsteuer | Bruttowert |
| ----------------------- | --------: | -----------------: | -------------: | ---------: |
| Design-Paket, 2 × 100 € |  200,00 € |               19 % |        38,00 € |   238,00 € |
| Handbuch                |   50,00 € |                7 % |         3,50 € |    53,50 € |
| Einmalige Summe         |  250,00 € | getrennt nach Satz |        41,50 € |   291,50 € |

Ein zusätzliches Angebot mit monatlicher Abrechnung wird getrennt ausgewiesen. Beispiel: 19,99 € netto + 3,80 € Mehrwertsteuer = 23,79 € pro Monat; bei drei Monaten 71,37 € brutto. Die Gesamtbetrachtung einschließlich obiger einmaliger Leistungen beträgt 362,87 € brutto.

## Rechenregeln

- Steuerbasis ist der endgültige Nettopreis nach Gruppenrabatten, Mindestpreisen und Aktionen, einschließlich bereits berechneter Vor-Ort- und Reisekosten.
- Die Mehrwertsteuer wird **einmal pro Anfrageposition und Abrechnungsperiode** kaufmännisch auf die kleinste Einheit der gewählten Währung gerundet. Die Positionswerte werden anschließend addiert. Deshalb kann die Summe geringfügig von einer einmaligen Steuerberechnung über die gesamte Nettosumme abweichen.
- Laufzeitsummen multiplizieren die bereits gerundeten Monatswerte. Unterschiedliche Laufzeiten werden separat ausgewiesen. Bei unterschiedlichen Laufzeiten wird kein gemeinsamer vollständiger Vergleichspreis erfunden.
- Ein verkauftes Paket verwendet **einen Steuersatz für die gesamte Paketposition**. Die Steuersätze seiner enthaltenen Angebote werden nicht zusätzlich oder anteilig angewendet. Wenn rechtlich unterschiedliche Steuersätze nötig sind, diese Leistungen als separate Angebote verkaufen. Zuschläge und Reisen übernehmen ebenfalls den Satz ihrer Position.
- Offene Leistungen und offene Reisekosten bleiben offen; ihre Steuer wird nicht als 0 € ausgegeben. Die Gesamtdarstellung weist darauf hin, dass diese Kosten und ihre Steuer noch hinzukommen.
- Bei Bruttoanzeige werden regulärer Preis und Aktionspreis brutto dargestellt. Die angezeigte Ersparnis ist ihre Differenz, einschließlich der Rundung. Die aufklappbaren Rechenschritte verwenden ausdrücklich Nettobeträge.
- Ein Satz von 0 % fügt keine Behauptung über Steuerbefreiung oder Kleinunternehmerstatus hinzu. Eine gegebenenfalls erforderliche Begründung bleibt Teil der eigenen Texte.

## Gespeicherte Anfragen und Mails

Die serverseitig signierte Vorschau enthält Steuerbeträge, Sätze und Anzeigeart. Ändert sich der Preisstand vor dem Absenden, muss der Besucher die neue Berechnung prüfen. Der Browser darf keine eigenen Preise oder Steuersätze vorgeben.

Gespeichert werden die damaligen Beträge und Beschriftungen. Änderungen der aktuellen Steuerkonfiguration verändern weder alte Anfragen noch erneut versandte Mails. Das gilt auch für eine nachträglich geänderte Anzeigeart. Anfragen aus früheren Versionen erhalten nicht rückwirkend eine Steuerberechnung.

Die Aufschlüsselung erscheint automatisch in Standardmails. In Pro wird sie über den vorhandenen Platzhalter **`[offerweave_quote]`** in die gestaltete E-Mail eingefügt. Der Datenexport einer Anfrage enthält den vollständigen gespeicherten Steuerstand; der Konfigurationsexport enthält Standard und Angebotsabweichungen.

## Grenzen

Kein Umsatzsteuer-Länderservice, keine USt-ID-Prüfung, kein Reverse-Charge-Automatismus und keine automatische Entscheidung zwischen B2B/B2C. Der Betreiber legt die zutreffenden Sätze und erforderlichen Erläuterungen fest. Die Einstellung ist unabhängig von den Steuern beim Kauf einer OfferWeave-Lizenz über Freemius.

## English quick start

Open **OfferWeave → Settings → Prices and conditions → VAT and price display**, enable VAT, enter the standard rate, and choose **Net, plus VAT** or **Gross, including VAT**. Keep entering all price rules and fixed discounts as net amounts. An empty offer-specific VAT field inherits the standard rate; an explicit zero overrides it. The full feature is available in Free, Pro and Owner.

VAT is rounded per item and billing period after discounts, then added for totals. Packages and their surcharges use the package rate. Recurring charges and different terms remain separate. Saved requests and emails retain their original tax snapshot. Review your custom price notices after changing the display. OfferWeave does not determine tax rules by country or create invoices.
