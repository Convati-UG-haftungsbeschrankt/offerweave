# Bilder in Angebotskarten – OfferWeave 1.5.0

Jedes Angebot kann ein eigenes Bild erhalten. Die Bildgestaltung funktioniert global für alle Angebote und mit individuellen Abweichungen pro Angebot. Vorhandene Angebote starten ohne Bild und behalten ihre Textdarstellung. Preise, Auswahl und Anfrageformular funktionieren weiterhin unabhängig vom Bild.

## Bild auswählen

1. **OfferWeave → Angebote** öffnen und das gewünschte Angebot wählen.
2. Unter **Inhalt und Darstellung → Angebotsbild** auf **Bild aus Mediathek wählen** klicken. Ein vorhandenes Bild auswählen oder über den WordPress-Dialog hochladen.
3. Mit **Bild für dieses Angebot verwenden** übernehmen.
4. Einen passenden **Alt-Text** eintragen. Er beschreibt das Motiv für Bildschirmleser. Ohne eigenen Text verwendet das Plugin den Mediathek-Alt-Text beziehungsweise den Angebotsnamen. Bei rein dekorativen Motiven den entsprechenden Schalter aktivieren; dann bleibt das `alt`-Attribut leer.
5. Über **Bildplatzierung und Design bearbeiten** die Gestaltung öffnen. **Änderungen speichern** veröffentlicht die Zuordnung und Gestaltung.

JPEG, PNG, GIF, WebP und AVIF werden als Mediathek-Bilder unterstützt. Ob ein Format hochgeladen und in mehrere Größen umgerechnet werden kann, hängt auch von WordPress und der Bildverarbeitung des Hosters ab. Das Plugin verwendet den normalen WordPress-Upload und erweitert keine Uploadrechte.

Ein Angebot kann genau ein Bild haben. Durch erneute Mediathek-Auswahl wird es ersetzt. **Bild aus Angebot entfernen** entfernt ausschließlich die Zuordnung; die Datei bleibt in der Mediathek. Auch andere Angebote, die dasselbe Bild verwenden, bleiben bestehen.

Alternativ lässt sich eine **direkte HTTP-/HTTPS-Bildadresse** eintragen. Zum Wechsel von einem Mediathek-Bild zunächst dessen Zuordnung entfernen. Die Adresse muss auf eine öffentlich erreichbare Bilddatei zeigen. Das Plugin lädt sie nicht auf deinen Server herunter; der Browser ruft sie beim angegebenen Anbieter ab. Verwende bevorzugt deine eigene Mediathek. Bei externen Adressen entstehen Verbindungen zu diesem Anbieter, und die Verfügbarkeit hängt von ihm ab. Im Produktivbetrieb HTTPS verwenden.

## Bild gestalten

Unter **OfferWeave → Design** befindet sich der Bereich **Bilder** mit 21 Einstellungen. Die Mediathek-Auswahl ist auch direkt im Designeditor erreichbar. Sie gilt immer für das gerade gewählte Vorschauangebot. Der **Geltungsbereich** darüber legt fest, ob die Gestaltung global oder individuell bearbeitet wird.

Bei einzelnen Angeboten aktiviert **Individuell anpassen** den jeweiligen Regler. Ohne diesen Schalter erbt das Angebot den globalen Wert. Du kannst beispielsweise überall Bilder oben anzeigen und nur ein bestimmtes Angebot mit einem seitlichen Bild versehen.

| Einstellung                      | Wirkung                                                                                                                                             |
| -------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------- |
| Angebotsbild anzeigen            | Bild ein- oder ausblenden, ohne seine Zuordnung zu löschen                                                                                          |
| Platzierung                      | Oben vor dem Inhalt, unter dem Titel, unter der Beschreibung, unten vor dem Button, links oder rechts neben dem Inhalt                              |
| Bildbreite                       | 10–100 % der verfügbaren Breite innerhalb der Bildumgebung                                                                                          |
| Maximale Bildbreite              | Zusätzliche Begrenzung in Pixeln; `0` bedeutet keine zusätzliche Begrenzung                                                                         |
| Bildhöhe                         | Eigene Pixelwerte für breite und schmale Karten; `0` erhält das natürliche Seitenverhältnis                                                         |
| Spaltenanteil                    | Breite der Bildspalte bei seitlicher Platzierung, 20–60 %                                                                                           |
| Horizontale Ausrichtung          | Links, mittig oder rechts innerhalb des verfügbaren Bildbereichs                                                                                    |
| Bildfüllung                      | **Fläche füllen** schneidet überstehende Bildteile ab. **Vollständig einpassen** erhält das ganze Motiv und kann freie Hintergrundflächen erzeugen. |
| Fokuspunkt horizontal/vertikal   | Bestimmt, welcher Teil beim Zuschneiden sichtbar bleibt; jeweils 0–100 %                                                                            |
| Abstände oben/rechts/unten/links | Abstand zwischen Bild und seiner Umgebung in der Karte                                                                                              |
| Bildumrandung                    | Stärke, Farbe und Art: durchgezogen, gestrichelt, gepunktet oder keine                                                                              |
| Bildrundung und Bildschatten     | Runde Ecken und drei Schattenstärken                                                                                                                |
| Hintergrund                      | Fläche hinter transparenten Bildern oder freien Bereichen beim Einpassen                                                                            |

Bei den Positionen **unter dem Titel** und **unter der Beschreibung** liegt das Bild innerhalb des Textbereichs. Dessen Karteninnenabstände gelten zusätzlich. Bei **oben**, **unten**, **links** und **rechts** lässt sich der Bildabstand zur Kartenkante unabhängig vom Textinnenabstand einstellen. Für ein Bild bis zur Kartenkante die betreffenden Bildabstände auf `0` setzen; die äußere Kartenumrandung bleibt erhalten.

**Karten bis einschließlich 560 px Breite:** Seitliche Bilder rücken automatisch über den Text und verwenden die Höhe für schmale Karten. Das gilt auch für eine schmale Karte innerhalb eines Desktop-Rasters. So bleibt neben dem Bild keine zu schmale Textspalte. Oberhalb dieser Breite greift die seitliche Anordnung mit der eingestellten Spaltenbreite.

Die Vorschau zeigt dieselbe Darstellung wie die Website. Desktop-, Tablet- und Smartphonebreite lassen sich umschalten. Änderungen an Designwerten erscheinen sofort, Bildwechsel nach kurzer Aktualisierung. Erst Speichern ändert die veröffentlichte Konfiguration. Die Vorschau löst keine Anfrage und keinen Mailversand aus. Wird ein neues Bild in der Mediathek hochgeladen, ist die Datei dort bereits gespeichert; die Zuordnung zur Angebotskarte bleibt bis zum Speichern ein Entwurf.

## Drei Startvarianten

**Breites Bild oben auf einer Karte:** Platzierung oben, Breite 100 %, Höhe schmale Karten etwa 160–200 px, Füllung „Fläche füllen“. Oben/links/rechts jeweils `0` Abstand ergibt eine Bildfläche bis zur Kartenumrandung. Bei beschnittenen Motiven den Fokuspunkt anpassen.

**Kleines Symbol über dem Titel:** Platzierung oben, maximale Breite etwa 100–140 px, Höhe `0`, Ausrichtung mittig und „Vollständig einpassen“. Ausreichend Abstand zum Kartentitel lassen.

**Seitliches Motiv für ein einzelnes breites Angebot:** Platzierung links oder rechts, Spaltenanteil etwa 30–40 %, maximale Bildbreite nach Bedarf und 16 px Bildabstände. Auf schmalen Karten erscheint das Bild automatisch oben. Die konkrete Motivhöhe anhand der Vorschau einstellen.

Das sind Startwerte für den Editor, keine fest vorgeschriebene Gestaltung.

## Darstellung, Umzug und bestehende Daten

Mediathek-Bilder verwenden die WordPress-Bildgrößen, intrinsische Abmessungen und – sofern mehrere passende Varianten vorhanden sind – responsive Bildquellen. `loading="lazy"` und asynchrones Dekodieren sind aktiviert. Direkte externe Adressen haben keine von WordPress ermittelten Größenvarianten. Bei wichtigen Bildinhalten den Alt-Text pflegen; reine Textinformationen aus dem Angebot sollten zusätzlich als echter Kartentext erhalten bleiben.

Bei einem Ladefehler blendet die Karte den Bildbereich aus und bleibt mit Preis und Auswahlbutton nutzbar. Wird eine zugeordnete Datei aus der Mediathek gelöscht, erscheint die Website ebenfalls ohne Bild. Vor dem nächsten Speichern der Konfiguration ist die fehlende Zuordnung zu entfernen oder zu ersetzen; der Editor meldet das fehlende Bild.

Der Konfigurationsexport übernimmt Bildadresse, Alt-Text und Gestaltung, aber **keine Bilddateien und keine lokalen Mediathek-IDs**. Auf einer anderen WordPress-Installation kann deshalb keine gleichlautende ID versehentlich ein fremdes Bild auswählen. Nach dem Import wird das Bild zunächst über die übertragene Adresse geladen. Für einen vollständigen Umzug Bilder auf die neue Website übertragen und anschließend in der neuen Mediathek auswählen. Solange eine alte Adresse verwendet wird, muss diese erreichbar bleiben.

Die Erweiterung betrifft Angebotskarten auf der Website und deren Designvorschau. Auswahlzusammenfassungen und Kundenmails erhalten dadurch keine zusätzlichen Bilder. Historische Anfragen und gespeicherte Preise werden nicht geändert. Bildrechte und die Auswahl der eigenen Motive bleiben beim Betreiber.

Die mitgelieferten Angebotsbeispiele erhalten nicht automatisch neue Motive. Die Bilder in den Entwicklungsscreenshots sind ausdrücklich als lokale Beispielbilder gekennzeichnet und dienen der Funktionsprüfung.

## Nachweise

- [Analyse und ausgeführter Prompt](ANGEBOTSBILDER-ANALYSE-PROMPT.md)
- [Aktueller Prüfbericht](PRUEFBERICHT.md)

## SVG-Dateien

SVGs werden als Angebotsbilder und Kurzinfo-Icons unterstützt. Neue Uploads erfolgen über die WordPress-Mediathek und müssen dort zugelassen sein, etwa durch eine SVG-Erweiterung mit Bereinigung. OfferWeave verändert keine Uploadrechte. Bereits vorhandene SVG-Dateien funktionieren auch ohne Raster-Vorschaubilder. Die Darstellung verwendet Bild-Elemente; SVG-Code wird nicht in die Seite eingebettet.
