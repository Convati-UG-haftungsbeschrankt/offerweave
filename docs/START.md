# OfferWeave einrichten

Neue öffentliche Installationen starten mit einem leeren Angebotskatalog. Unter **Angebote** eigene Leistungen, Preise und Bilder eintragen. Free unterstützt Festpreise, Pro zusätzlich Staffelpreise, Gruppen und Auswahlpakete. Alle Eingabepreise sind Nettobeträge; unter Einstellungen → Preise und Bedingungen lassen sich Standardwährung, Steuerberechnung und Anzeige konfigurieren.

1. Unter **Einstellungen** Anbieterangaben, Zielgruppe, Empfänger, Datenschutz- und Auswahlseite pflegen.
2. Eigene Angebote mit eindeutigen IDs anlegen. Die vier Bereiche trennen Inhalt und Darstellung, Preis und Menge, Pakete und Optionen sowie Veröffentlichung. Unter Preis und Menge lässt sich der Entwurf ohne Speicherung einer Anfrage berechnen. Paketpreise und Einzelpreise sind unabhängig. Der Schalter „Einzeln im Katalog anbieten“ steuert die selbstständige Auswahl; ausgeblendete Angebote können Bestandteile eines Pakets bleiben.
3. `[offerweave_catalog]` für den Katalog einsetzen. `[offerweave_catalog id="IHRE-ID"]` zeigt ein bestimmtes Angebot; den Platzhalter durch eine tatsächlich vergebene ID ersetzen.
4. `[offerweave_add id="IHRE-ID"]` ergänzt die Auswahl auf einer eigenen Detailseite.
5. `[offerweave_request]` auf der Anfrageseite einfügen; `[offerweave_selection]` zeigt dort, wo er ausdrücklich eingesetzt wird, den Auswahlbutton.
6. Unter Einstellungen die Dokumentart und Hinweistexte wählen: standardmäßig eine unverbindliche Preisübersicht, optional ein verbindliches Angebot mit Annahmefrist. E-Mails vor dem Einsatz prüfen. Ein Angebot ist noch keine Auftragsbestätigung oder Rechnung.
7. Gestaltung, mobile Ansicht, Spam-Schutz und einen vollständigen Anfrageablauf auf der eigenen Website prüfen.

Die Zielgruppe „Verbraucher oder gemischt“ verlangt konfigurierte Steuern und hebt Bruttopreise hervor. Das ersetzt keine Prüfung von Steuerfällen, Leistungsbedingungen oder Verbraucherinformationen für einen anschließenden Vertragsschluss. Ein B2B-Hinweis allein beweist keine Unternehmereigenschaft.

Die automatische Kundenmail ist bei neuen Installationen ausgeschaltet. Die Dokumentart schaltet sie nicht ein. Unter Einstellungen → Anfragecharakter und Anbieterangaben lassen sich die Texte beider Arten getrennt bearbeiten und die Standardtexte wiederherstellen. Leere Textfelder verwenden die Standardsprache der Anfrage; eigene Texte können unter Sprachen übersetzt werden. Die Annahmefrist eines verbindlichen Angebots beginnt mit der Anfrage und endet am berechneten Tag um 23:59 Uhr in der Website-Zeitzone. Erneuter Versand verlängert sie nicht. Anbieterangaben, Dokumentart, aufgelöste Texte und Datumsangaben werden mit jeder Anfrage gespeichert. Bestehende Anfragen werden durch neue Einstellungen nicht umgeschrieben. Beim unverbindlichen Dokument bleibt das optionale Neuberechnungsdatum eine Empfehlung und keine Annahmefrist.

Die Free-/Pro-Ausgaben enthalten keine Branchenvorlagen. Bereits gespeicherte Benutzerdaten werden bei einem Update oder Editionswechsel nicht durch leere Startwerte ersetzt. Exportieren Sie vor größeren Änderungen Ihre Konfiguration und sichern Sie die WordPress-Datenbank. Exportdateien können eigene Anbieterangaben enthalten und sind entsprechend zu behandeln.

## Eingaben frei platzieren (Pro ab 2.24.0)

Links oder rechts: `[offerweave_controls connect="studio"]`

Angebotsspalte: `[offerweave_catalog category="studio" connect="studio" controls="external"]`

Beide Shortcodes auf derselben Seite, genau ein Paar pro Verbindungsname. Kategorie-ID ersetzen. In gemischten Katalogen bleiben die Mengen je Angebot getrennt. Ohne eindeutigen Partner bleiben Eingaben am bisherigen Ort. Details und Bilder: Handbuch → Seiten und Shortcodes.

Ab 2.24.0 gehören diese Layoutfunktionen zu Pro. Free zeigt bei vorhandenen Verknüpfungen wieder normale Katalogeingaben; externe Blöcke bleiben leer. Für nur gemeinsame Felder `controls="external-shared"` verwenden. Pro-Einstellungen und Seiteninhalte bleiben gespeichert.

## PHP-Ausgabe und Bedienung ohne JavaScript

Angebotskarten, Preise, Bilder und Detail-Links stehen bereits im ursprünglichen HTML. JavaScript ergänzt Aktualisierungen ohne Seitenwechsel. Ohne JavaScript ändern Besucher die Werte und klicken auf **Preis aktualisieren**; Auswahl und Anfrage verwenden normale PHP-Formulare. Ein optional aktiviertes externes CAPTCHA benötigt weiterhin JavaScript.

Die seitübergreifende Auswahl verwendet ein notwendiges Cookie mit einer zufälligen, zwei Stunden gültigen Kennung und einen begrenzten serverseitigen Zwischenspeicher. Kontaktfelder werden weder im Cookie noch in der URL gespeichert. Nehmen Sie Seiten mit OfferWeave-Shortcodes und Antworten für `offerweave_selection_*` vom gemeinsam genutzten Seiten-/CDN-Cache aus; leeren Sie bestehende Einträge nach einem Update.
