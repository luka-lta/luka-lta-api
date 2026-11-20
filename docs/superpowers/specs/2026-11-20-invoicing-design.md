# Kunden, Angebote und Rechnungen (Kleingewerbe) — Design

Stand: 2026-11-20 · Repos: `luka-lta-api`, `luka-lta-backend`

## Absicht

Luka betreibt ein Kleingewerbe unter der **Kleinunternehmerregelung (§ 19 UStG)**. Er will im Admin-Dashboard
Kunden verwalten und für sie **Angebote** und **Rechnungen als PDF** erstellen — inklusive einer optionalen
**Anzahlungsrechnung** pro Auftrag. Es geht um echte Geschäftsdokumente: Nummern, Inhalt und PDF müssen
steuerlich sauber und nachträglich unveränderlich sein. Eine bereits ausgestellte Rechnung muss eingepflegt
werden können, damit die Nummernfolge nahtlos weiterläuft.

## Ziele

- Kunden anlegen, bearbeiten, archivieren.
- Angebote (`AN-JAHR-0001`) und Rechnungen (`RE-JAHR-0001`) mit Positionen erstellen, als PDF herunterladen.
- Ablauf Angebot → optional eine Anzahlungsrechnung → Schlussrechnung mit Abzug der Anzahlung.
- Status bezahlt/offen pro Rechnung, inkl. Zahlungsdatum; Überfälligkeit sichtbar.
- Bestehende (extern erstellte) Rechnung importieren: Metadaten + Original-PDF.
- Storno festgeschriebener Rechnungen über Storno-Rechnung.

## Nicht-Ziele (jetzt)

- **Kein E-Mail-Versand** — nur Download. Versand kommt später; das Datenmodell darf ihn nicht verbauen.
- **Keine Umsatzsteuer**: keine Steuersätze, kein Netto/Brutto. Nur Beträge + § 19-Hinweis.
- Keine E-Rechnung (XRechnung/ZUGFeRD) — Kleinunternehmer sind vom Ausstellen ausgenommen (Annahme, siehe unten).
- Kein Mahnwesen, keine Buchhaltung/EÜR-Export, keine wiederkehrenden Rechnungen.
- `RevenueFinanceCard` / `BusinessKpis` im Dashboard laufen weiter auf `demoData.ts`; die Anbindung an echte
  Rechnungsdaten ist ein späterer Schritt.

## Anti-Ziele (wäre ein Fehlschlag, selbst wenn es „läuft“)

- Eine festgeschriebene Rechnung lässt sich ändern oder löschen, oder ihr PDF ändert sich nachträglich
  (z. B. weil Firmendaten oder Kundenadresse später angepasst wurden).
- Lücken oder Doppelungen im `RE`-Nummernkreis.
- Rechnungs-PDFs oder Kundendaten öffentlich erreichbar (z. B. per `public-read` in MinIO oder ungeschützter Route).
- Bankverbindung, Steuernummer oder Kundendaten landen in Code, Seeds, Doku oder Git-History.

## Entscheidungen von Luka

| Thema | Entscheidung |
|---|---|
| Steuer | Kleinunternehmer § 19 UStG, keine USt |
| Rechnungsnummer | `RE-JAHR-0001` (z. B. `RE-2026-0001`) |
| Bestandsrechnung | importieren: Kunde, Nummer, Datum, Betrag, Status + Original-PDF; **kein** neu erzeugtes PDF |
| Anzahlung | optional, **höchstens eine** pro Auftrag, Höhe frei je Auftrag |
| Anzahlung unbezahlt | **Schlussrechnung sperren**, bis die Anzahlung bezahlt ist |
| Zustellung | nur Download, Versand später |
| PDF-Technik | serverseitig in der API mit **mPDF** (Dependency freigegeben) |
| Tests | **keine** Test-Suite einführen (kein PHPUnit); Verifikation über `just lint-path`, Build, manuelles Durchspielen |

## Approaches (PDF-Erzeugung)

1. **PHP-Bibliothek in der API (HTML-Vorlage → PDF, mPDF)** — *gewählt.* Kein neuer Container, läuft in
   `php-fpm-api`. Begrenztes CSS (kein Flex/Grid), für Tabellen-Layout ausreichend. mPDF statt dompdf wegen
   zuverlässigerer Tabellen über Seitenumbrüche und Schriftunterstützung.
2. **Gotenberg/Chromium-Container** — pixelgenau, aber zusätzlicher Dienst im Homelab und Umbau an
   `docker-compose`. Für Rechnungen unnötig.
3. **PDF im Browser (Dashboard)** — verworfen: Unveränderlichkeit nicht serverseitig garantiert, schwere
   Frontend-Dependency.

## Fachmodell

### Nummernkreise

- Zwei Kreise: `RE` (alle Rechnungsarten inkl. Anzahlung, Schluss, Storno) und `AN` (Angebote).
- Format `<PREFIX>-<JAHR>-<4-stellig, zero-padded>`; Zähler **pro Kreis und Jahr**, startet jährlich bei 1.
  Mehr als 9999 pro Jahr: Nummer wird einfach breiter (kein Fehler).
- Jahr = Jahr des **Rechnungs-/Angebotsdatums**, nicht des Erstellzeitpunkts.
- Nummer wird erst beim **Festschreiben** vergeben (Entwürfe haben keine Nummer → gelöschte Entwürfe reißen keine Lücke).
- Vergabe in einer Transaktion über eine Sequenz-Tabelle mit `SELECT … FOR UPDATE`.
- Import: Nummer wird manuell eingegeben, muss dem Format entsprechen und eindeutig sein; ist sie höher als der
  aktuelle Zählerstand ihres Jahres, wird der Zähler darauf angehoben. Lukas bestehende Rechnung hat bereits
  das Format `RE-JAHR-0001`.
- *Entscheidung Claude:* Das Jahr in der Nummer muss dem Jahr des Rechnungsdatums entsprechen, auch beim Import.

### Kunde

Firma (optional), Ansprechpartner/Name, Straße, PLZ, Ort, Land (Default `DE`), E-Mail (optional),
USt-IdNr. (optional), Notiz. Mindestens Firma **oder** Name muss gesetzt sein.
Kunden mit Dokumenten werden nur **archiviert** (ausgeblendet), nie gelöscht.

### Firmendaten (Einstellungen, eine Zeile)

Name/Firma, Inhaber, Anschrift, E-Mail, Telefon, Steuernummer, Bank (Name, IBAN, BIC), optional Logo
(MinIO, privat), Standard-Zahlungsziel in Tagen (Default 14), Standard-Fuß-/Schlusstext.
Liegen in der DB, im Dashboard pflegbar. Festschreiben ist nur möglich, wenn die Pflichtfelder
(Name, Anschrift, Steuernummer, IBAN) gesetzt sind.

### Positionen (Angebot und Rechnung gleich)

Laufende Nr., Beschreibung (mehrzeilig), Menge (Dezimal, 2 Nachkommastellen), Einheit (Freitext,
z. B. „Std.“, „pausch.“), Einzelpreis in **Cent (int)**. Zeilensumme = round(Menge × Einzelpreis), kaufmännisch.
Beträge im Code als Value Object (`Money` in Cent), nie als float.

### Angebot

- Status: `draft` → `sent` → `accepted` | `rejected`. Nummer + PDF bei Übergang `draft → sent`.
- Felder: Kunde, Datum, gültig bis (Default +30 Tage), Einleitungs-/Schlusstext, Positionen.
- Bearbeiten nur im Status `draft`. Ein `sent`-Angebot ist eingefroren; für Änderungen neues Angebot anlegen
  (Duplizieren-Funktion).
- Aus einem `accepted` Angebot: Anzahlungsrechnung, Schlussrechnung oder normale Rechnung erzeugen.

### Rechnung

Arten (`kind`): `standard`, `deposit` (Anzahlung), `final` (Schluss), `cancellation` (Storno).
Status: `draft` → `open` (festgeschrieben) → `paid`; zusätzlich `cancelled` (durch Storno).

- **Pflichtangaben auf dem PDF**: vollständiger Name/Anschrift beider Seiten, Steuernummer, Rechnungsdatum,
  Rechnungsnummer, Menge/Art der Leistung, **Leistungsdatum oder -zeitraum**, Betrag,
  Hinweis „Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.“
- Felder: Kunde, Rechnungsdatum, Leistungsdatum **oder** Leistungszeitraum (von/bis), Zahlungsziel (Tage, Default
  aus Einstellungen) → Fälligkeitsdatum, Einleitungs-/Schlusstext, Positionen, optional Bezug auf Angebot.
- **Festschreiben** (`draft → open`): Nummer vergeben, **Snapshot** von Kunden- und Firmendaten in der Rechnung
  speichern, PDF erzeugen und in MinIO ablegen — alles in einer Transaktion; schlägt das PDF fehl, wird nichts
  festgeschrieben und keine Nummer verbraucht.
- Danach unveränderlich. Einzige Änderungen: als bezahlt markieren (mit Zahlungsdatum), bezahlt zurücknehmen,
  stornieren.
- **Überfällig** = `open` und Fälligkeitsdatum < heute; wird berechnet, nicht gespeichert.
- Entwürfe dürfen gelöscht werden.

### Anzahlung & Schlussrechnung

- Anzahlungsrechnung nur aus einem `accepted` Angebot. Betrag frei: Eingabe in € oder % des Angebotsbetrags,
  gespeichert wird immer der Cent-Betrag. Eine Position, z. B. „Anzahlung zu Angebot AN-2026-0003“.
- Pro Angebot höchstens **eine nicht stornierte** Anzahlungsrechnung.
- Schlussrechnung aus dem Angebot: übernimmt die Angebotspositionen (im Entwurf änderbar) und zeigt darunter:
  Gesamt − Anzahlung (mit Nummer + Datum der Anzahlungsrechnung) = **Restbetrag**. Fälliger Betrag = Restbetrag.
- **Sperre**: Schlussrechnung lässt sich nur festschreiben, wenn die Anzahlung `paid` ist. Sonst `409` mit
  Meldung, im Dashboard Hinweis „Gesperrt: Anzahlung RE-… noch offen“. Entwurf anlegen geht trotzdem.
- Ist die Anzahlung storniert, entfällt der Abzug; die Schlussrechnung verhält sich wie eine normale Rechnung.
- Anzahlungsrechnung kann nicht storniert werden, solange eine festgeschriebene, nicht stornierte
  Schlussrechnung auf sie verweist.

### Storno

- Nur für `open`/`paid` Rechnungen. Erzeugt eine `cancellation`-Rechnung mit eigener `RE`-Nummer, gleichen
  Positionen mit **negativen** Beträgen, Verweis „Storno zu RE-…“ im PDF. Original → `cancelled`.
- Stornos selbst sind nicht stornierbar.
- *Entscheidung Claude:* Das Storno **spiegelt das Original exakt**. Bei einer Schlussrechnung werden auch der
  Anzahlungsabzug gespiegelt, die Summe ist also −Restbetrag. Bei einer importierten Rechnung (ohne Positionen)
  gibt es eine Position „Storno zu Rechnung RE-…“ über −Gesamtbetrag. Ausstellerdaten kommen in beiden Fällen
  aus den aktuellen Firmendaten (Snapshot beim Erzeugen des Stornos).
- *Entscheidung Claude:* Storno-Rechnungen haben **keinen Zahlungsstatus**. Sie bekommen den Status `issued`,
  zählen nicht zum offenen Betrag und sind nie überfällig. Eine stornierte, vorher bezahlte Rechnung behält ihr
  `paid_at`. Eine Rückzahlung an den Kunden passiert außerhalb des Systems.
- *Entscheidung Claude:* Eine Anzahlung kann nicht auf „unbezahlt“ zurückgesetzt werden, solange eine
  festgeschriebene, nicht stornierte Schlussrechnung auf sie verweist (`409`).

### Import einer Bestandsrechnung

- Eigener Dialog: Kunde (bestehend oder neu), Nummer, Rechnungsdatum, Gesamtbetrag, Status (`open`/`paid`,
  ggf. Zahlungsdatum), Fälligkeitsdatum, Original-PDF (Pflicht, nur `application/pdf`, max. 10 MiB).
- Wird als `standard` mit Flag `is_imported` gespeichert, ohne Positionen; das hochgeladene PDF ist *das* PDF.
- Danach gleiche Regeln wie festgeschriebene Rechnungen (unveränderlich, bezahlt markieren, stornieren).

## Technik

### API (`luka-lta-api`)

- Domain-Verzeichnisse: `src/Api/Customer/`, `src/Api/Offer/`, `src/Api/Invoice/`,
  `src/Api/BusinessSettings/` (je `Action/`, `Service/`), Value Objects unter `src/Value/Business/…`,
  Repositories flach in `src/Repository/`, Exceptions erben `ApiException`.
- PDF: `composer require mpdf/mpdf`. Vorlagen als einfache PHP-Templates (keine Template-Engine), Layout
  angelehnt an DIN 5008 (Absenderzeile, Anschriftfeld, Infoblock rechts mit Nummer/Datum, Positionstabelle,
  Summenblock, Fußzeile mit Firmen- und Bankdaten). Drei Varianten über ein Template: Angebot, Rechnung
  (inkl. Anzahlung/Schluss), Storno. Entwurfs-Vorschau mit Wasserzeichen „ENTWURF“, wird nicht gespeichert.
- MinIO: Objekte unter `business/offers/<id>.pdf`, `business/invoices/<id>.pdf`, Logo unter
  `business/logo.<ext>` — **ohne `public-read`** (der bestehende `S3Repository::uploadFile` setzt `public-read`;
  dafür eine eigene private Upload-Methode). Download nur über authentifizierte API-Route, die das Objekt streamt.
- Alle Routen in Gruppen mit `AuthMiddleware` (Admin-JWT). Keine API-Key-Routen. `AuthMiddleware` prüft
  keine Rollen. Das ist bewusst so, weil Luka der einzige Dashboard-Nutzer ist und bleibt. Kommen weitere
  Accounts dazu, brauchen die Business-Routen vorher eine eigene Berechtigungsprüfung.
- Endpunkte (Vorschlag):
  - `GET/POST /customers`, `GET/PATCH /customers/{id}`, `POST /customers/{id}/archive`
  - `GET/PUT /business/settings`, `POST /business/settings/logo`
  - `GET/POST /offers`, `GET/PATCH/DELETE /offers/{id}`, `POST /offers/{id}/send`,
    `POST /offers/{id}/status` (accepted/rejected), `POST /offers/{id}/duplicate`, `GET /offers/{id}/pdf`
  - `POST /offers/{id}/invoices` (body: `kind` = standard|deposit|final, bei deposit Betrag oder Prozent)
  - `GET/POST /invoices`, `GET/PATCH/DELETE /invoices/{id}` (nur Entwurf), `POST /invoices/{id}/finalize`,
    `POST /invoices/{id}/payment` (bezahlt + Datum), `DELETE /invoices/{id}/payment`,
    `POST /invoices/{id}/cancel`, `GET /invoices/{id}/pdf`, `POST /invoices/import` (multipart)
- Listen mit Filter nach Status/Kunde/Jahr; Rechnungsliste liefert `isOverdue`, offenen Betrag.
- Migrations als neue Dateien mit Präfix `business_` (Tabellen u. a. `business_customers`, `business_settings`,
  `business_number_sequences`, `business_offers`, `business_offer_items`, `business_invoices`,
  `business_invoice_items`); Fremdschlüssel in eigener Datei `zzz_business_foreign_keys.sql` (läuft nach
  allen Tabellen; `business_invoice_items` sortiert vor `business_invoices`, deshalb keine Inline-FKs). Bestehende Migrationen nicht
  ändern. Produktiv werden die Dateien manuell eingespielt (initdb läuft nur auf leerem Volume).
- Snapshots als JSON-Spalten (`customer_snapshot`, `issuer_snapshot`) an Angebot und Rechnung.

### Dashboard (`luka-lta-backend`)

- `src/api/{customers,offers,invoices,business-settings}/{endpoints,hooks}.ts`, Zod-Schemas in
  `src/feature/<domain>/schema/`, Features unter `src/feature/{customers,offers,invoices}/`.
- Navigation: unter „Business“ die Seiten **Kunden**, **Angebote**, **Rechnungen**, **Einstellungen**
  (Firmendaten). Bestehende `BusinessPage` bleibt.
- Rechnungsliste: Nummer, Kunde, Datum, Betrag, Status-Badge (Entwurf/offen/überfällig/bezahlt/storniert),
  Filter. Detailansicht mit Aktionen passend zum Status (Festschreiben, PDF, bezahlt markieren, Storno).
- Angebotsdetail: Buttons „Anzahlungsrechnung“, „Schlussrechnung“, „Rechnung“ je nach Zustand; gesperrte
  Schlussrechnung mit sichtbarem Grund.
- Positions-Editor mit Live-Summe; Beträge als Cent, Anzeige `de-DE` in €.
- PDF-Download über `api` mit `responseType: 'blob'` (Token im Header, kein Link mit Token in der URL).
- Destruktive/endgültige Aktionen (Festschreiben, Storno) mit Bestätigungsdialog, der die Endgültigkeit nennt.

## Annahmen (bitte prüfen)

- Kleinunternehmer stellen keine E-Rechnung aus (Stand 2026). Falls Kunden das verlangen → eigenes Folgeprojekt.
- Zähler startet jährlich neu.
- Angebote und Rechnungen in Euro, Sprache Deutsch.

## Dem Umsetzer überlassen

- Genaue Tabellen-/Spaltennamen, Indizes, Value-Object-Zuschnitt.
- PDF-Gestaltung im Rahmen DIN 5008, Schrift aus den mPDF-Bordmitteln.
- UI-Details (Tabellenkomponenten, Dialog-Aufbau) im bestehenden Dashboard-Stil.
