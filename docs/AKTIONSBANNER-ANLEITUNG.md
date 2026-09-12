# Aktionsbanner und Aktionspreise – ab OfferWeave 2.9.0

Preisaktionen und der vollständige Designer gehören zu Pro und zur privaten Ausgabe für die Eigennutzung. Free erhält dadurch keine zusätzlichen Preisregeln.

## Banner frei platzieren

**Ab 2.9.0 wird kein Banner mehr automatisch über Katalog, Detailauswahl, Gesamtrechner oder Anfrage eingeblendet.** Bereits aktive Rabatte gelten weiterhin. Soll ein Banner sichtbar sein, füge an der gewünschten Stelle einen WordPress-Shortcode-Block ein:

```text
[offerweave_promotions]
```

Das zeigt alle passenden aktiven Aktionen. Für genau eine Aktion verwende ihre tatsächliche ID:

```text
[offerweave_promotions promotion="sommeraktion"]
```

`sommeraktion` ist ein Beispiel und muss zur ID unter **OfferWeave → Sonderangebote** passen. Dort findest du den fertigen Shortcode und den Button **Shortcode kopieren**. Eine Umbenennung der Aktions-ID erfordert auch die Anpassung bereits eingefügter Shortcodes.

Bestehende Filter bleiben nutzbar und lassen sich mit der Aktions-ID kombinieren:

```text
[offerweave_promotions category="design"]
[offerweave_promotions offer="designpaket"]
```

Der Shortcode kann in Seiten, Beiträge oder shortcodefähige Template-/Widgetbereiche eingefügt werden. Der WordPress-Bereich bestimmt den verfügbaren Platz. Es entstehen dadurch keine Angebotskarten und keine zusätzliche Auswahloberfläche. Katalog und Banner können unabhängig voneinander auf derselben oder auf unterschiedlichen Seiten stehen.

## Sichtbarkeit und Rabatt getrennt einstellen

Unter **Sonderangebote**:

- **Aktion im eingestellten Zeitraum aktivieren** steuert den Rabatt.
- **Diesen Banner für die Shortcode-Ausgabe aktivieren** steuert die Banneranzeige.
- Zeitraum und Angebotszuordnung müssen passen. Eine Aktion ohne passende sichtbare Angebote wird nicht ausgegeben.

Zukünftige, beendete und deaktivierte Aktionen bleiben auf der Website unsichtbar. Ist nur die Bannerausgabe ausgeschaltet, zeigen die betroffenen Karten weiterhin den Aktionspreis. Ein unbekannter, ansonsten korrekt geschriebener Aktionsbezeichner zeigt keinen anderen Banner als Ersatz.

## Betroffene Angebote auswählen – ab 2.10.0

Unter **Sonderangebote → Betroffene Angebote** kannst du auch große Kataloge gezielt durchsuchen:

- **Angebote suchen** findet Namen, Kategorien und eindeutige IDs; Groß-/Kleinschreibung und Akzente werden berücksichtigt. Mehrere Suchbegriffe müssen gemeinsam passen.
- **Kategorie** begrenzt die Ansicht auf eine Angebotsgruppe.
- **Alle Angebote**, **Nur ausgewählte** und **Nicht ausgewählt** grenzen die Ansicht weiter ein. Suchtext, Kategorie und Auswahlfilter können kombiniert werden.
- Die obere Zeile zeigt, wie viele Angebote insgesamt zugeordnet sind und wie viele Treffer die aktuelle Ansicht enthält.

Die Liste bleibt in ihrer Höhe begrenzt und lässt sich scrollen. Jede Zeile enthält Name, Kategorie und ID; ausgewählte Angebote sind blau hinterlegt. Ein Hinweis kennzeichnet deaktivierte Angebote. Deren Zuordnung bleibt erhalten, ohne dass ein deaktiviertes Angebot durch die Auswahl aktiviert wird.

**Treffer auswählen** und **Treffer abwählen** wirken ausschließlich auf die aktuell gefilterten Angebote. Bereits ausgewählte, unsichtbare Angebote bleiben erhalten. **Filter zurücksetzen** ändert lediglich die Ansicht. Erst eine Checkbox oder Sammelaktion ändert die Zuordnung; veröffentlicht wird sie mit **Konfiguration speichern**.

Die Suche allein erzeugt keine ungespeicherten Änderungen. Beim Wechsel zu einer anderen Preisaktion beginnen die Filter wieder ohne Einschränkung. Checkboxen lassen sich mit Tab erreichen und mit der Leertaste umschalten; Fokus und Listenposition bleiben während der Auswahl erhalten.

## Banner gestalten

Öffne **OfferWeave → Design → Geltungsbereich**:

- **Alle Aktionsbanner – globales Design** legt die gemeinsamen Vorgaben fest.
- **Banner: [Aktionsname]** erlaubt Abweichungen für genau diese Aktion. Alternativ öffnet **Diesen Banner gestalten** direkt bei der Aktion den passenden Bereich.

Zur Auswahl stehen:

| Bereich       | Einstellmöglichkeiten                                                                                                                                                                                                |
| ------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Bannerfarben  | Hintergrund, optionaler Verlauf mit zweiter Farbe/Winkel, Text, Titel, Rabatt, Umrandung, Akzentlinie und Kennzeichnung                                                                                              |
| Bannerschrift | Schriftfamilie, Titelgröße und -gewicht, Text-, Rabatt-, Hinweis- und Kennzeichnungsgröße; eigene Mobilgrößen                                                                                                        |
| Banneraufbau  | Zeilen- oder Stapelanordnung der Kopfzeile, links/mittig/rechts, maximale Breite, Rahmenart/-stärke, Akzentlinie, Rundung, Schatten, Abstände innerhalb des Banners, vier Innenabstände und Außenabstände oben/unten |

Die maximale Breite **0** nutzt die verfügbare Breite des WordPress-Bereichs. Auf Smartphones gilt der eigene mobile Innenabstand. Die Kennzeichnung „Preisaktion“ lässt sich ausblenden; Zeitraum, Leistungszuordnung und Rabattbedingungen bleiben sichtbar.

Bei einer einzelnen Aktion schaltet **Anpassen** den jeweiligen abweichenden Wert frei. Haken entfernen übernimmt wieder die globale Vorgabe. **Alle Abweichungen entfernen** setzt ausschließlich die gewählte Aktion zurück. **Standarddesign laden** im globalen Bannerbereich setzt die Bannerwerte zurück und erhält das Kartendesign.

Die Live-Vorschau speichert nichts. Sie kann auch Entwürfe und zukünftige Aktionen darstellen, ohne sie auf der Website zu aktivieren. Erst **Konfiguration speichern** übernimmt die Gestaltung. Im globalen Bannerbereich wird eine eindeutig als Vorschau bezeichnete Beispielaktion gezeigt.

## Aktionspreise auf Karten gestalten

Wähle im gleichen Designer **Alle Angebote – globales Design** oder ein bestimmtes Angebot und öffne **Aktionspreise**. Dort kannst du bearbeiten:

- Farbe, Schriftgröße und Schriftgewicht des reduzierten Betrags sowie seine mobile Größe;
- Farbe und Größe des durchgestrichenen regulären Betrags;
- Größe der Beschriftung „Aktionspreis“ und Abstände;
- Farben, Größe, Rundung und Innenabstände der Aktionskennzeichnung.

Für eine sofort sichtbare Vorschau wähle rechts **Aktionspreis (Beispiel)**. Diese Vorschau verwendet einen berechneten Beispielrabatt von 20 % und legt keine echte Aktion an. Für einzelne Angebote funktioniert die Vererbung über **Anpassen** genauso wie beim Banner.

Die Karten und die aktuelle Auswahl zeigen den regulären Betrag durchgestrichen über dem hervorgehobenen Aktionsbetrag. Preiseinheit, Steuerhinweis, Ersparnis und Zeitraum stehen geordnet daneben beziehungsweise darunter. Der Vergleich bezieht sich auf den gleichen Umfang und die eingestellte Netto-/Bruttoanzeige. Das System führt kein historisches Vergleichspreisarchiv.

## Nach dem Update

1. Das passende ZIP der aktuellen Version als Update installieren.
2. Editor und Frontend neu laden; gegebenenfalls eigenen Seiten-/Asset-Cache leeren.
3. Bestehende Aktionen kontrollieren und Banner-Shortcodes an den gewünschten Stellen setzen.
4. Banner und Aktionspreise gestalten, Vorschau für Desktop und Smartphone prüfen und speichern.

Die komplette Startvorlage muss dafür nicht erneut geladen werden. Vorhandene Angebote, Anfragen und Preise bleiben erhalten. Die neuen Designwerte werden mit der Konfiguration exportiert und importiert. Bei Konfigurationen aus älteren Versionen werden fehlende Werte ergänzt und vorhandene individuelle Farben berücksichtigt.
