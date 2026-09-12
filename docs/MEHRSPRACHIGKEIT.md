# Sprachen in OfferWeave

Die Oberfläche verwendet die WordPress-Textdomain `offerweave` und englische Ausgangstexte. **Free** erhält verfügbare Übersetzungen als WordPress.org-Sprachpakete; Updates erfolgen unter **Dashboard → Aktualisierungen**. **Pro und Owner** verwenden dieselben WordPress-Funktionen und registrieren ihre mitgelieferten deutschen und englischen Sprachdateien als extern vertriebene Plugins. Es gibt keine eigene Lade- oder Zusammenführungslogik. Fehlt eine Übersetzung, erscheint der englische Ausgangstext.

Eigene Angebotstexte und Mailvorlagen bleiben gespeicherte Inhalte und werden unter **OfferWeave → Sprachen** gepflegt. Preise, Angebots-IDs, Bilder und Gestaltung gelten gemeinsam für die angebotenen deutschen und englischen Inhaltsfassungen. Die Oberflächensprache übersetzt diese Texte nicht automatisch.

## Eine Sprache oder zwei Sprachversionen

Auf einer einsprachigen Website verwendet OfferWeave die Sprache unter **WordPress → Einstellungen → Allgemein → Sprache der Website**. Für die Verwaltung gilt die Sprache Ihres WordPress-Benutzerprofils. Eine englische Verwaltung kann daher auch eine deutsche Website bearbeiten.

Für Sprachwechsel in REST-Vorschauen und gespeicherten Anfragen muss die entsprechende Sprache auch in WordPress installiert sein. Installieren Sie sie unter **Einstellungen → Allgemein → Sprache der Website**; danach können Sie zur bisherigen Websitesprache zurückkehren. Ohne installiertes Core-Sprachpaket verwendet WordPress englische Oberflächentexte; eigene gespeicherte Inhalte bleiben erhalten.

Für zwei Sprachversionen installieren und konfigurieren Sie **Polylang** zusätzlich. Die kostenlose Version reicht für die hier beschriebene Anbindung. Legen Sie Deutsch (`de_DE`) und Englisch (`en_US`) sowie die gewünschten übersetzten Seiten an und verknüpfen Sie die Seiten in Polylang. OfferWeave erkennt die Sprache der angezeigten Seite automatisch. Die Sprachumschaltung der Website richten Sie in Polylang ein.

Die Anbindung verwendet die öffentlichen Polylang-Funktionen. Angebotstexte werden direkt in OfferWeave gepflegt; es entstehen keine doppelten Angebote oder Preislisten in Polylang.

## Texte übersetzen

1. Öffnen Sie **OfferWeave → Sprachen**. Bei bestehenden Installationen ist die Sprache der Originaltexte Deutsch.
2. Wählen Sie Englisch als Übersetzungssprache und den gewünschten Bereich, zum Beispiel ein bestimmtes Angebot, Formularfelder oder E-Mail-Vorlagen.
3. Links sehen Sie den Originaltext, rechts tragen Sie die Übersetzung ein. Speichern Sie die Änderungen über den gemeinsamen Speicherbutton.
4. Prüfen Sie anschließend die englische Website. Originaltexte bearbeiten Sie weiterhin unter Angebote, Formularfelder, Einstellungen oder E-Mail.

Auch eigene Einheitenbezeichnungen im Singular und Plural sind übersetzbar. Übersetzbar sind Angebotsnamen, Beschreibungen, Merkmale, Kategorienamen, Bild-Alternativtexte, Formularbeschriftungen, Platzhalter, die sichtbaren Texte einer Auswahlliste, öffentliche Hinweise, Seitenlinks sowie Mailbetreff, Mailinhalt und Fußzeile. Ein englischer Bild-Alternativtext benötigt kein zweites Bild.

Die Einstellung **Sprache der Originaltexte** beschreibt, in welcher Sprache die bestehenden Originaltexte geschrieben sind. Eine Änderung übersetzt die Texte nicht automatisch. Wenn Ihre Originaltexte englisch sind, wählen Sie Englisch und pflegen die deutsche Variante im Sprachenbereich.

Leere Übersetzungen verwenden den Originaltext. Ändert sich ein Originaltext, wird die bisherige Übersetzung zur Prüfung markiert. Sie bleibt im Editor erhalten, wird aber erst nach Bearbeitung oder Bestätigung wieder veröffentlicht. So bleibt beispielsweise eine alte Leistungsbeschreibung nach einer inhaltlichen Änderung nicht unbemerkt aktiv. Bis zur Prüfung kann auf der fremdsprachigen Seite der Originaltext erscheinen.

Unveränderte mitgelieferte Beispieltexte erhalten beim Update ihre englischen Varianten. Eigene Texte werden nicht automatisch übersetzt und nicht durch Beispiele ersetzt. Kontrollieren Sie vor der Veröffentlichung insbesondere eigene Angebotsbeschreibungen und Mailtexte.

## Katalog, Auswahl und Seitenlinks

Verwenden Sie auf beiden Sprachversionen dieselben Angebots-IDs:

```text
[offerweave_catalog]
[offerweave_catalog offer="service-basis"]
[offerweave_request]
```

`service-basis` ist eine Beispiel-ID. Verwenden Sie die unter Angebote angezeigte ID Ihres Angebots. Kategorien und Shortcode-Namen werden nicht übersetzt. Den optionalen `title`-Text eines Shortcodes schreiben Sie direkt auf der jeweiligen WordPress-Seite in deren Sprache.

Hinterlegen Sie die deutsche Anfrageseite wie gewohnt unter Einstellungen und verknüpfen Sie deren englische Seite in Polylang. Dasselbe gilt für die Datenschutzseite. OfferWeave verwendet auf der englischen Seite automatisch die verknüpften Seiten. Alternativ können Sie unter Sprachen ausdrücklich eine URL je Sprache eintragen. Ein ausdrücklich übersetzter Link hat Vorrang.

Auf Sprachversionen derselben Domain bleibt die Auswahl im Browser beim Sprachwechsel erhalten. Teilnehmerzahlen und Preise werden weiter mit denselben Regeln berechnet. Polylang-Konfigurationen mit unterschiedlichen Domains je Sprache teilen wegen der Browsergrenzen keinen lokalen Auswahlspeicher; dafür ist keine Übertragung implementiert.

## Anfragen und E-Mails

Eine Anfrage speichert ihre Sprache zusammen mit den damals angezeigten Leistungen, Preisen, Formularbeschriftungen und Mailvorlagen. Die Sprache ist in den Anfragedetails sichtbar. Automatischer Versand, Vorschau einer gespeicherten Anfrage und späterer manueller Versand verwenden diese gespeicherte Sprache. Änderungen an aktuellen Übersetzungen schreiben alte Anfragen nicht um.

Bestandsanfragen ohne Sprachangabe werden als deutsch behandelt. Der konfigurierte Versandmodus bleibt bestehen: aus, automatisch, nur bei vollständig berechneten Preisen oder nach manueller Prüfung.

Die Mailgestaltung gilt gemeinsam für beide Sprachen. Die übersetzten Mailtexte bearbeiten Sie unter **Sprachen → E-Mail-Vorlagen**. HTML-Grundformatierung ist dort möglich. Behalten Sie `[offerweave_quote]` genau einmal bei; die vorhandenen Platzhalter wie `[brand]`, `[reference]` und `[field id="name"]` bleiben unverändert. Ändern Sie technische Formular-IDs nur mit anschließender Prüfung der Platzhalter in beiden Sprachen.

Unter **E-Mail → Vorschau → Sprache der Vorschau** können Sie Deutsch und Englisch unabhängig von Ihrer Verwaltungssprache prüfen. Die Vorschau verwendet Beispieldaten. Die Vorschau einer gespeicherten Anfrage verwendet dagegen deren tatsächlichen gespeicherten Stand. Eine Testmail wird nur über den vorhandenen ausdrücklichen Testmail-Button an das eigene WordPress-Konto verschickt.

Absender, Empfänger, SMTP- und Spam-Schutzeinstellungen sind gemeinsame Einstellungen und können nicht über Übersetzungen verändert werden.

## Vor der Veröffentlichung

- Eigene Texte in beiden Sprachen prüfen; offene und zur Prüfung markierte Übersetzungen bearbeiten.
- Katalog- und Anfrageseiten in Polylang verknüpfen und Seitenlinks prüfen.
- Die gleiche Auswahl in Deutsch und Englisch berechnen: Zahlenwerte müssen gleich sein, die Darstellung kann beispielsweise `690,00 €` oder `€690.00` lauten.
- Beide Mailvorschauen kontrollieren und eine eigene Testanfrage einschließlich späterer Mailvorschau durchspielen.
- Seiten mit OfferWeave-Shortcodes sowie Antworten für das Auswahl-Cookie `offerweave_selection_*` vom gemeinsam genutzten Seiten-/CDN-Cache ausnehmen und bestehende Einträge nach dem Update leeren. PHP-Seiten mit Auswahl und REST-Antworten werden als privat und nicht speicherbar ausgeliefert. Andere Sprachseiten weiterhin getrennt behandeln.

## Für Entwickler und weitere Sprachen

Alle eingebauten Texte verwenden die WordPress-Textdomain `offerweave`: PHP-Gettext, `wp.i18n` und `wp_set_script_translations`. Free hat keinen eigenen Textdomain-Ladehook und keine MO/PO/POT- oder Gettext-JSON-Dateien im Installationspaket. `languages/scripts.json` beschreibt ausschließlich Skriptdateien; `default-content.json` enthält Inhaltsvorlagen, keine Oberflächenübersetzungsengine.

Für Pro/Owner registriert `load_plugin_textdomain` am `init` die üblichen Sprachdateien. PHP-Sprachpakete im WordPress-Sprachverzeichnis haben nach Core Vorrang; unübersetzte Pro-Texte können dann englisch bleiben. Bei JavaScript prüft Core den ausdrücklich registrierten Paketpfad zuerst. Diese unterschiedlichen Core-Prioritäten werden nicht durch eigene Filter oder Merges verändert. Vorschauen erhalten ihre Jed-Daten über `load_script_textdomain`. Sprachwechsel nutzen `switch_to_locale` und `restore_previous_locale`.

Die öffentlichen REST-Sprachparameter und der Editor für eigene Inhalte unterstützen `de_DE` und `en_US`. WordPress kann zusätzliche Oberflächensprachpakete verwenden; dies erweitert nicht automatisch die Inhaltssprachenliste. Regionale Sprachpakete werden genau nach WordPress-Regeln geladen, ohne eigene Umleitung auf Deutsch. WPML und TranslatePress sind nicht als getestet ausgewiesen.

Die Übersetzungsquellen liegen im Entwicklungsrepository. `tools/languages.py` erzeugt reguläre PO-/MO-/Jed-Dateien; das ist ein Entwicklungswerkzeug und wird nicht installiert. `editions/free/languages/offerweave-de_DE.po` enthält nur die aus der expliziten Free-Komposition extrahierten Texte zur Übernahme in translate.wordpress.org. Veröffentlichung und Freigabe dort folgen dem normalen WordPress-Übersetzungsprozess. Free installiert diese Entwicklungskataloge nicht selbst.

Referenzen: [WordPress-Internationalisierung](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/), [Sprachladen seit WordPress 6.7](https://make.wordpress.org/core/2024/10/21/i18n-improvements-6-7/), [WordPress-Sprachwechsel](https://developer.wordpress.org/reference/functions/switch_to_locale/).
